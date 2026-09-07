<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Dos reglas fiscales que el sistema no respetaba: serie+folio es único por
 * emisor, y una orden sólo puede tener un CFDI vigente a la vez.
 */
class FiscalIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    private function client(string $name = 'ACME'): Client
    {
        return Client::create([
            'legal_name' => $name, 'name' => $name, 'email' => strtolower($name) . '@test.com',
            'tax_id' => 'CHA150312AB1', 'tax_system' => '601', 'cfdi_use' => 'G03',
            'address_zip' => '06600', 'portal_active' => true,
        ]);
    }

    private function order(Client $client, string $series = 'F'): Order
    {
        return Order::createWithFolio([
            'client_id' => $client->id, 'series' => $series,
            'payment_form' => '03', 'payment_method' => 'PUE', 'use_cfdi' => 'G03',
            'billing_preference' => 'fiscal', 'status' => 'sent',
            'subtotal' => 1000, 'iva_amount' => 160, 'total' => 1160,
        ]);
    }

    // ── Folios ───────────────────────────────────────────

    /** Regresión: las recurrentes numeraban por cliente, así que se repetían. */
    public function test_folios_are_unique_across_clients(): void
    {
        $first = $this->order($this->client('Uno'));
        $second = $this->order($this->client('Dos'));

        $this->assertSame(1, $first->folio_number);
        $this->assertSame(2, $second->folio_number);
    }

    public function test_each_series_has_its_own_sequence(): void
    {
        $client = $this->client();

        $this->assertSame(1, $this->order($client, 'F')->folio_number);
        $this->assertSame(1, $this->order($client, 'V')->folio_number);
        $this->assertSame(2, $this->order($client, 'F')->folio_number);
    }

    /** El folio de una orden borrada ya se emitió y no puede reutilizarse. */
    public function test_a_deleted_order_does_not_release_its_folio(): void
    {
        $client = $this->client();
        $this->order($client)->delete();

        $this->assertSame(2, $this->order($client)->folio_number);
    }

    public function test_the_database_rejects_a_duplicate_folio(): void
    {
        $client = $this->client();
        $existing = $this->order($client);

        $this->expectException(UniqueConstraintViolationException::class);

        Order::create([
            'client_id' => $client->id, 'series' => $existing->series,
            'folio_number' => $existing->folio_number,
            'payment_form' => '03', 'payment_method' => 'PUE', 'use_cfdi' => 'G03',
            'status' => 'sent', 'subtotal' => 1, 'iva_amount' => 0, 'total' => 1,
        ]);
    }

    // ── CFDI vigente ─────────────────────────────────────

    /**
     * Regresión: al cancelar, la relación seguía devolviendo el documento
     * cancelado, así que isStamped() decía "no" y se podía timbrar sin fin.
     */
    public function test_reissuing_after_cancellation_shows_the_new_document(): void
    {
        $order = $this->order($this->client());

        $order->fiscalDocument()->create([
            'status' => 'valid', 'uuid' => 'UUID-PRIMERO', 'source' => 'facturapi', 'stamped_at' => now(),
        ]);
        $this->assertTrue($order->fresh()->isStamped());

        $order->fiscalDocument->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->assertFalse($order->fresh()->isStamped(), 'Tras cancelar debe poder reexpedirse.');

        $order->fiscalDocument()->create([
            'status' => 'valid', 'uuid' => 'UUID-SEGUNDO', 'source' => 'facturapi', 'stamped_at' => now(),
        ]);

        $reloaded = $order->fresh();
        $this->assertTrue($reloaded->isStamped(), 'El CFDI vigente debe volver a bloquear el timbrado.');
        $this->assertSame('UUID-SEGUNDO', $reloaded->fiscalDocument->uuid);
    }

    /** El CFDI cancelado se conserva: es el registro fiscal de lo ocurrido. */
    public function test_the_cancelled_document_is_kept_for_audit(): void
    {
        $order = $this->order($this->client());

        $order->fiscalDocument()->create(['status' => 'valid', 'uuid' => 'U1', 'source' => 'facturapi', 'stamped_at' => now()]);
        $order->fiscalDocument->update(['status' => 'cancelled']);
        $order->fiscalDocument()->create(['status' => 'valid', 'uuid' => 'U2', 'source' => 'facturapi', 'stamped_at' => now()]);

        $this->assertSame(2, $order->fresh()->fiscalDocuments()->count());
        $this->assertEqualsCanonicalizing(
            ['U1', 'U2'],
            $order->fresh()->fiscalDocuments()->pluck('uuid')->all()
        );
    }

    /** Con la relación arreglada, las descargas sirven el documento vigente. */
    public function test_downloads_resolve_the_current_document(): void
    {
        $order = $this->order($this->client());
        $order->fiscalDocument()->create(['status' => 'valid', 'uuid' => 'U1', 'source' => 'facturapi', 'stamped_at' => now()]);
        $order->fiscalDocument->update(['status' => 'cancelled']);
        $order->fiscalDocument()->create([
            'status' => 'valid', 'uuid' => 'U2', 'source' => 'finkok',
            'xml_path' => 'cfdi/finkok/U2.xml', 'stamped_at' => now(),
        ]);

        $this->assertSame('finkok', $order->fresh()->fiscalDocument->source);
    }
}
