<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Quote;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El folio se calculaba contando filas. Como el borrado de cotizaciones es
 * lógico pero el índice único no, borrar una dejaba el contador apuntando a un
 * folio ya emitido y a partir de ahí toda creación fallaba por clave duplicada.
 */
class QuoteNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    private function lead(): Lead
    {
        return Lead::create(['name' => 'Prospecto', 'email' => 'p@test.com', 'status' => 'nuevo']);
    }

    private function quote(Lead $lead): Quote
    {
        return Quote::createWithNumber([
            'lead_id' => $lead->id, 'subtotal' => 100, 'iva_percentage' => 16,
            'iva_amount' => 16, 'total' => 116, 'status' => 'borrador',
            'valid_until' => now()->addDays(30),
        ]);
    }

    public function test_numbers_are_sequential(): void
    {
        $lead = $this->lead();
        $year = now()->format('Y');

        $this->assertSame("COT-{$year}-0001", $this->quote($lead)->quote_number);
        $this->assertSame("COT-{$year}-0002", $this->quote($lead)->quote_number);
    }

    /** Regresión: tras borrar una cotización el siguiente folio chocaba. */
    public function test_deleting_a_quote_does_not_break_creation(): void
    {
        $lead = $this->lead();
        $this->quote($lead);
        $this->quote($lead);
        $third = $this->quote($lead);

        $third->delete();

        $next = $this->quote($lead);

        $year = now()->format('Y');
        $this->assertSame("COT-{$year}-0004", $next->quote_number);
    }

    /** Ni siquiera si se borran todas: el folio no debe reutilizarse. */
    public function test_numbers_are_never_reused_after_deleting_everything(): void
    {
        $lead = $this->lead();
        $used = [];
        foreach (range(1, 3) as $i) {
            $used[] = $this->quote($lead)->quote_number;
        }
        Quote::query()->delete();

        $next = $this->quote($lead);

        $this->assertNotContains($next->quote_number, $used);
    }

    /** Más allá de 9999 el folio crece en ancho sin romper el orden. */
    public function test_numbering_continues_past_four_digits(): void
    {
        $lead = $this->lead();
        $year = now()->format('Y');
        Quote::createWithNumber([
            'lead_id' => $lead->id, 'subtotal' => 1, 'iva_percentage' => 16,
            'iva_amount' => 0, 'total' => 1, 'status' => 'borrador',
            'valid_until' => now()->addDays(30),
        ])->update(['quote_number' => "COT-{$year}-9999"]);

        $this->assertSame("COT-{$year}-10000", $this->quote($lead)->quote_number);
    }

    /** El alta desde el panel sigue funcionando tras un borrado. */
    public function test_admin_can_create_a_quote_after_deleting_one(): void
    {
        $lead = $this->lead();
        $this->quote($lead)->delete();

        $category = ServiceCategory::create(['name' => 'Hosting', 'slug' => 'hosting']);
        $service = Service::create([
            'name' => 'Hosting básico', 'slug' => 'hosting-basico', 'description' => 'x',
            'price' => 1000, 'service_category_id' => $category->id, 'public' => true, 'active' => true,
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('secret'), 'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post('/panel/quotes', [
                'lead_id' => $lead->id,
                'items'   => [['service_id' => $service->id, 'quantity' => 1, 'unit_price' => 1000]],
            ])
            ->assertRedirect();

        $this->assertSame(2, Quote::withTrashed()->count());
    }
}
