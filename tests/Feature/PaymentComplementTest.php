<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentComplement;
use App\Models\Setting;
use App\Services\CfdiBuilderService;
use App\Services\InvoicingManager;
use App\Services\OrderFinalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Una factura timbrada como PPD obliga a emitir un Recibo Electrónico de Pago
 * por cada cobro. El CRM no emitía ninguno: el método de Facturapi estaba
 * escrito y no lo llamaba nadie, y con Finkok ni siquiera existía. Encima, las
 * órdenes nacidas de una cotización se marcaban PPD de fábrica, así que una
 * venta cobrada y liquidada se timbraba en parcialidades y quedaba debiendo un
 * complemento para siempre.
 */
class PaymentComplementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake(['*' => Http::response(['id' => 'inv_1', 'uuid' => 'UUID-REP', 'status' => 'valid'])]);
        Setting::set('facturapi_api_key', 'sk_test_123');
    }

    private function client(): Client
    {
        return Client::create([
            'legal_name' => 'Cliente PPD SA', 'name' => 'Cliente PPD',
            'email' => 'ppd@test.com', 'tax_id' => 'CHA150312AB1', 'tax_system' => '601',
            'cfdi_use' => 'G03', 'address_zip' => '06600',
            'facturapi_customer_id' => 'cus_1', 'portal_active' => true,
        ]);
    }

    private function order(array $attributes = []): Order
    {
        return Order::createWithFolio(array_merge([
            'client_id' => $this->client()->id, 'series' => 'F',
            'payment_form' => '99', 'payment_method' => 'PPD', 'use_cfdi' => 'G03',
            'billing_preference' => 'fiscal', 'status' => 'sent',
            'subtotal' => 1000, 'iva_amount' => 160, 'total' => 1160,
            'notes' => 'Compra directa: Consultoría',
        ], $attributes));
    }

    private function stamp(Order $order, string $source = 'facturapi'): Order
    {
        $order->fiscalDocument()->create([
            'source' => $source, 'status' => 'valid', 'stamped_at' => now(),
            'facturapi_invoice_id' => 'inv_orig',
            'facturapi_data' => ['uuid' => 'UUID-FACTURA'],
            'uuid' => $source === 'finkok' ? 'UUID-FACTURA' : null,
        ]);

        return $order->fresh();
    }

    private function payment(Order $order, array $attributes = []): Payment
    {
        return $order->payments()->create(array_merge([
            'amount' => $order->total, 'currency' => 'MXN', 'status' => 'approved',
            'payment_type' => 'bank_transfer', 'payment_method_id' => 'transfer',
            'paid_at' => now(),
        ], $attributes));
    }

    // ── La causa de raíz: PPD sobre una venta ya liquidada ──

    /**
     * Si se timbra al cobrar y el cobro liquida la orden, es una sola
     * exhibición: PUE. Así no nace ningún complemento que deber.
     */
    public function test_an_order_settled_at_stamping_time_is_stamped_as_pue(): void
    {
        $order = $this->order(['status' => 'draft']);

        app(OrderFinalizationService::class)->finalize($this->payment($order));

        $this->assertSame('PUE', $order->fresh()->payment_method);
        $this->assertSame(0, PaymentComplement::count());
    }

    /** Un cobro que no liquida la orden sigue siendo PPD de verdad. */
    public function test_a_partial_payment_keeps_the_order_as_ppd(): void
    {
        $order = $this->order(['status' => 'draft']);

        app(OrderFinalizationService::class)->finalize($this->payment($order, ['amount' => 500]));

        $this->assertSame('PPD', $order->fresh()->payment_method);
    }

    // ── El REP cuando la factura ya estaba timbrada ─────────

    public function test_a_payment_on_a_stamped_ppd_invoice_issues_its_complement(): void
    {
        $order   = $this->stamp($this->order());
        $payment = $this->payment($order);

        app(OrderFinalizationService::class)->finalize($payment);

        $complement = PaymentComplement::firstOrFail();
        $this->assertSame('valid', $complement->status);
        $this->assertSame('UUID-REP', $complement->uuid);
        $this->assertSame($payment->id, $complement->payment_id);

        Http::assertSent(function ($request) {
            if (($request['type'] ?? null) !== 'P') {
                return false;
            }
            $pago = $request['complements'][0]['data'][0];

            return $pago['payment_form'] === '03'
                && $pago['amount'] === 1160.0
                && $pago['related_documents'][0]['uuid'] === 'UUID-FACTURA'
                && $pago['related_documents'][0]['last_balance'] === 1160.0
                && $pago['related_documents'][0]['installment'] === 1;
        });
    }

    /** La factura sigue siendo la de ingreso: el REP no la sustituye. */
    public function test_the_complement_does_not_replace_the_invoice(): void
    {
        $order = $this->stamp($this->order());

        app(OrderFinalizationService::class)->finalize($this->payment($order));

        $this->assertSame('inv_orig', $order->fresh()->fiscalDocument->facturapi_invoice_id);
        $this->assertTrue($order->fresh()->isStamped());
    }

    /** Un pago genera un complemento y sólo uno. */
    public function test_the_complement_is_issued_once_per_payment(): void
    {
        $order   = $this->stamp($this->order());
        $payment = $this->payment($order);
        $manager = app(InvoicingManager::class);

        $manager->issuePaymentComplement($order, $payment);
        $manager->issuePaymentComplement($order->fresh(), $payment);

        $this->assertSame(1, PaymentComplement::count());
        Http::assertSentCount(1);
    }

    public function test_a_pue_invoice_owes_no_complement(): void
    {
        $order = $this->stamp($this->order(['payment_method' => 'PUE']));

        app(OrderFinalizationService::class)->finalize($this->payment($order));

        $this->assertSame(0, PaymentComplement::count());
        $this->assertFalse($order->fresh()->owesPaymentComplement());
    }

    public function test_an_unstamped_invoice_owes_no_complement(): void
    {
        $order = $this->order();

        $this->assertNull(app(InvoicingManager::class)->issuePaymentComplement($order, $this->payment($order)));
        $this->assertSame(0, PaymentComplement::count());
    }

    // ── Lo que no se puede emitir, se ve ────────────────────

    /** El SAT no admite "99 por definir" en un REP: no se inventa una forma. */
    public function test_an_undefined_payment_form_is_recorded_as_failed(): void
    {
        $order   = $this->stamp($this->order());
        $payment = $this->payment($order, ['payment_type' => 'manual', 'payment_method_id' => 'efectivo']);

        app(InvoicingManager::class)->issuePaymentComplement($order, $payment);

        $complement = PaymentComplement::firstOrFail();
        $this->assertSame('failed', $complement->status);
        $this->assertStringContainsString('99', $complement->error);
        $this->assertTrue($order->fresh()->owesPaymentComplement());
        Http::assertNothingSent();
    }

    public function test_an_invoice_without_uuid_is_recorded_as_failed(): void
    {
        $order = $this->order();
        $order->fiscalDocument()->create([
            'source' => 'facturapi', 'status' => 'valid', 'stamped_at' => now(),
            'facturapi_invoice_id' => 'inv_orig', 'facturapi_data' => [],
        ]);
        $order = $order->fresh();

        app(InvoicingManager::class)->issuePaymentComplement($order, $this->payment($order));

        $this->assertSame('failed', PaymentComplement::firstOrFail()->status);
        $this->assertTrue($order->fresh()->owesPaymentComplement());
    }

    // ── El REP de Finkok ────────────────────────────────────

    /**
     * El CFDI tipo P se construye sin sellar para poder revisarlo: un CSD de
     * pruebas del SAT no está al alcance de la suite.
     */
    public function test_the_finkok_complement_is_a_well_formed_type_p_cfdi(): void
    {
        Setting::set('company_rfc', 'EKU9003173C9');
        Setting::set('company_legal_name', 'ESCUELA KEMPER URGATE');
        Setting::set('company_tax_system', '601');
        Setting::set('company_zip', '26015');

        $order   = $this->stamp($this->order(), source: 'finkok');
        $payment = $this->payment($order);

        $complement = PaymentComplement::create([
            'order_id' => $order->id, 'payment_id' => $payment->id,
            'source' => 'finkok', 'amount' => $payment->amount,
            'installment' => 1, 'status' => 'pending',
        ]);

        $xml = app(CfdiBuilderService::class)->paymentComplementCreator($complement)->asXml();

        $this->assertStringContainsString('TipoDeComprobante="P"', $xml);
        $this->assertStringContainsString('Moneda="XXX"', $xml);
        $this->assertStringContainsString('Total="0"', $xml);
        $this->assertStringContainsString('UsoCFDI="CP01"', $xml);
        $this->assertStringContainsString('ClaveProdServ="84111506"', $xml);
        $this->assertStringContainsString('ClaveUnidad="ACT"', $xml);

        // El complemento en sí: el pago y el documento que liquida.
        $this->assertStringContainsString('pago20:Pagos', $xml);
        $this->assertStringContainsString('Version="2.0"', $xml);
        $this->assertStringContainsString('MontoTotalPagos="1160.00"', $xml);
        $this->assertStringContainsString('TotalTrasladosBaseIVA16="1000.00"', $xml);
        $this->assertStringContainsString('TotalTrasladosImpuestoIVA16="160.00"', $xml);
        $this->assertStringContainsString('FormaDePagoP="03"', $xml);
        $this->assertStringContainsString('IdDocumento="UUID-FACTURA"', $xml);
        $this->assertStringContainsString('NumParcialidad="1"', $xml);
        $this->assertStringContainsString('ImpSaldoAnt="1160.00"', $xml);
        $this->assertStringContainsString('ImpPagado="1160.00"', $xml);
        $this->assertStringContainsString('ImpSaldoInsoluto="0.00"', $xml);
        $this->assertStringContainsString('ObjetoImpDR="02"', $xml);
    }

    /** Una parcialidad declara el impuesto en proporción a lo pagado. */
    public function test_a_partial_payment_prorates_the_declared_tax(): void
    {
        Setting::set('company_rfc', 'EKU9003173C9');
        Setting::set('company_tax_system', '601');
        Setting::set('company_zip', '26015');

        $order   = $this->stamp($this->order(), source: 'finkok');
        $payment = $this->payment($order, ['amount' => 580]);

        $complement = PaymentComplement::create([
            'order_id' => $order->id, 'payment_id' => $payment->id,
            'source' => 'finkok', 'amount' => 580, 'installment' => 1, 'status' => 'pending',
        ]);

        $xml = app(CfdiBuilderService::class)->paymentComplementCreator($complement)->asXml();

        $this->assertStringContainsString('MontoTotalPagos="580.00"', $xml);
        $this->assertStringContainsString('TotalTrasladosBaseIVA16="500.00"', $xml);
        $this->assertStringContainsString('TotalTrasladosImpuestoIVA16="80.00"', $xml);
        $this->assertStringContainsString('ImpSaldoInsoluto="580.00"', $xml);
    }

    /** Un complemento que falta o falló tiene que verse en el panel. */
    public function test_the_panel_shows_a_pending_complement(): void
    {
        $admin = \App\Models\User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('secret'), 'role' => 'admin',
        ]);

        $order   = $this->stamp($this->order());
        $payment = $this->payment($order, ['payment_type' => 'manual', 'payment_method_id' => 'efectivo']);

        app(InvoicingManager::class)->issuePaymentComplement($order, $payment);

        $this->actingAs($admin)->get("/panel/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Complementos de pago')
            ->assertSee('Parcialidad 1')
            ->assertSee('99 (por definir)', escape: false);
    }
}
