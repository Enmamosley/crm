<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El webhook de DM Champ es el canal por el que entran los prospectos de
 * WhatsApp. Tenía tres fallos que lo dejaban inservible: descartaba en
 * silencio todos los contactos nuevos, no dejaba rastro en el historial al
 * cambiar de estado, y reventaba al registrar una cita.
 */
class DmChampWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // el observer de DmChamp no debe salir a la red
    }

    private function send(string $event, array $data)
    {
        return $this->postJson('/api/webhooks/dmchamp', ['event' => $event, 'data' => $data]);
    }

    private function newContact(string $phone, ?string $email = null, string $first = 'Ana')
    {
        return $this->send('new_contact', [
            'contactPhone'     => $phone,
            'contactEmail'     => $email,
            'contactFirstName' => $first,
            'contactLastName'  => 'Pérez',
        ]);
    }

    /**
     * Regresión: con un solo lead sin correo, el `orWhere('email', null)` se
     * convertía en `email IS NULL` y todos los contactos siguientes se perdían.
     */
    public function test_contacts_without_email_do_not_block_later_contacts(): void
    {
        $this->newContact('+525511111111')->assertOk();
        $this->assertSame(1, Lead::count());

        $this->newContact('+525522222222', first: 'Beto')->assertOk();

        $this->assertSame(2, Lead::count());
        $this->assertTrue(Lead::where('phone', '+525522222222')->exists());
    }

    public function test_the_same_contact_is_not_created_twice(): void
    {
        $this->newContact('+525511111111')->assertOk();
        $this->newContact('+525511111111')->assertOk();

        $this->assertSame(1, Lead::count());
    }

    /** El mismo número en otro formato es el mismo contacto. */
    public function test_phone_formats_resolve_to_the_same_contact(): void
    {
        $this->newContact('5512345678')->assertOk();
        $this->newContact('+52 55 1234 5678')->assertOk();
        $this->newContact('525512345678')->assertOk();

        $this->assertSame(1, Lead::count());
        $this->assertSame('+525512345678', Lead::first()->phone);
    }

    public function test_a_contact_identified_only_by_email_is_created(): void
    {
        $this->send('new_contact', ['contactEmail' => 'ana@test.com', 'contactFirstName' => 'Ana'])->assertOk();

        $this->assertSame(1, Lead::count());
        $this->assertNull(Lead::first()->phone);
    }

    public function test_a_new_lead_is_born_with_status_history(): void
    {
        $this->newContact('+525511111111')->assertOk();

        $this->assertDatabaseHas('lead_status_histories', [
            'lead_id'    => Lead::first()->id,
            'old_status' => null,
            'new_status' => 'nuevo',
            'changed_by' => 'dmchamp',
        ]);
    }

    /** Regresión: la etiqueta cambiaba el estado sin registrar el cambio. */
    public function test_tagging_a_contact_records_the_status_change(): void
    {
        $this->newContact('+525511111111')->assertOk();
        $lead = Lead::first();

        $this->send('contact_tagged', ['contactPhone' => '5512345678', 'tag' => 'cotizado'])->assertOk();
        $this->send('contact_tagged', ['contactPhone' => '+525511111111', 'tag' => 'cotizado'])->assertOk();

        $this->assertSame('cotizado', $lead->fresh()->status);
        $this->assertDatabaseHas('lead_status_histories', [
            'lead_id'    => $lead->id,
            'old_status' => 'nuevo',
            'new_status' => 'cotizado',
            'changed_by' => 'dmchamp',
        ]);
    }

    /** La misma etiqueta repetida no debe ensuciar el historial. */
    public function test_repeating_a_tag_does_not_duplicate_history(): void
    {
        $this->newContact('+525511111111')->assertOk();
        $lead = Lead::first();

        $this->send('contact_tagged', ['contactPhone' => '+525511111111', 'tag' => 'cerrado'])->assertOk();
        $this->send('contact_tagged', ['contactPhone' => '+525511111111', 'tag' => 'cerrado'])->assertOk();

        $this->assertSame(2, $lead->statusHistory()->count()); // alta + un cambio
    }

    /** Regresión: escribía la columna `note`, que no existe, y devolvía 500. */
    public function test_booking_an_appointment_stores_the_note(): void
    {
        $this->newContact('+525511111111')->assertOk();

        $this->send('appointment_booked', [
            'contactPhone'    => '+525511111111',
            'appointmentDate' => '2026-10-01 10:00',
        ])->assertOk();

        $note = LeadNote::first();
        $this->assertNotNull($note, 'La cita no dejó nota en el lead.');
        $this->assertStringContainsString('2026-10-01 10:00', $note->content);
        $this->assertSame('DM Champ', $note->author);
    }
}
