<?php

namespace Tests\Feature;

use App\Helpers\MenuHelper;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El panel sólo exigía sesión iniciada: cualquier usuario, de cualquier rol,
 * podía leer, editar y borrar todos los leads, cotizaciones y clientes. El
 * sistema de permisos existía, tenía pantalla de administración y nunca se
 * consultaba.
 */
class PanelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    private function user(string $role, array $permissions = []): User
    {
        $user = User::create([
            'name' => ucfirst($role), 'email' => "{$role}." . uniqid() . '@test.com',
            'password' => bcrypt('secret'), 'role' => $role,
        ]);

        foreach ($permissions as $permission) {
            Permission::create(['user_id' => $user->id, 'permission' => $permission]);
        }

        return $user;
    }

    private function lead(?User $assignee = null): Lead
    {
        return Lead::create([
            'name' => 'Prospecto', 'email' => uniqid() . '@test.com', 'status' => 'nuevo',
            'assigned_to' => $assignee?->id,
        ]);
    }

    private function quote(Lead $lead): Quote
    {
        return Quote::createWithNumber([
            'lead_id' => $lead->id, 'subtotal' => 100, 'iva_percentage' => 16,
            'iva_amount' => 16, 'total' => 116, 'status' => 'borrador',
            'valid_until' => now()->addDays(30),
        ]);
    }

    private function client(): Client
    {
        return Client::create([
            'legal_name' => 'ACME', 'email' => 'acme@test.com', 'tax_system' => '601',
            'cfdi_use' => 'G03', 'portal_active' => true,
        ]);
    }

    // ── El administrador conserva todo ───────────────────

    public function test_an_admin_reaches_every_section(): void
    {
        $admin = $this->user('admin');
        $lead = $this->lead();

        $this->actingAs($admin)->get('/panel/leads')->assertOk();
        $this->actingAs($admin)->get("/panel/leads/{$lead->id}")->assertOk();
        $this->actingAs($admin)->get('/panel/leads/create')->assertOk();
        $this->actingAs($admin)->get('/panel/quotes')->assertOk();
        $this->actingAs($admin)->get('/panel/clients')->assertOk();
    }

    // ── Contabilidad no gestiona prospectos ──────────────

    /** Regresión: un usuario de contabilidad podía editar y borrar cualquier lead. */
    public function test_accounting_cannot_manage_leads_or_quotes(): void
    {
        $accounting = $this->user('accounting');
        $lead = $this->lead();
        $quote = $this->quote($lead);

        $this->actingAs($accounting)->get('/panel/leads')->assertForbidden();
        $this->actingAs($accounting)->get("/panel/leads/{$lead->id}")->assertForbidden();
        $this->actingAs($accounting)->get('/panel/leads/create')->assertForbidden();
        $this->actingAs($accounting)->delete("/panel/leads/{$lead->id}")->assertForbidden();
        $this->actingAs($accounting)->get("/panel/quotes/{$quote->id}/edit")->assertForbidden();

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'deleted_at' => null]);
    }

    /** Pero sí conserva lo suyo: facturación y consulta de cotizaciones. */
    public function test_accounting_keeps_its_own_sections(): void
    {
        $accounting = $this->user('accounting');
        $lead = $this->lead();
        $quote = $this->quote($lead);

        $this->actingAs($accounting)->get('/panel/quotes')->assertOk();
        $this->actingAs($accounting)->get("/panel/quotes/{$quote->id}")->assertOk();
        $this->actingAs($accounting)->get('/panel/orders')->assertOk();
        $this->actingAs($accounting)->get('/panel/clients')->assertOk();
    }

    // ── Comerciales ──────────────────────────────────────

    /** Sin permisos propios, un comercial hereda los de su rol y sigue trabajando. */
    public function test_sales_inherits_its_role_defaults(): void
    {
        $sales = $this->user('sales');
        $lead = $this->lead();

        $this->actingAs($sales)->get('/panel/leads')->assertOk();
        $this->actingAs($sales)->get("/panel/leads/{$lead->id}")->assertOk();
        $this->actingAs($sales)->get('/panel/quotes')->assertOk();
    }

    public function test_sales_cannot_reach_admin_only_sections(): void
    {
        $sales = $this->user('sales');

        $this->actingAs($sales)->get('/panel/settings')->assertForbidden();
        $this->actingAs($sales)->get('/panel/services')->assertForbidden();
        $this->actingAs($sales)->get('/panel/users')->assertForbidden();
    }

    // ── Visibilidad restringida ──────────────────────────

    /** Marcar `leads.view_own` deja de ser decorativo. */
    public function test_view_own_limits_the_listing_and_the_detail(): void
    {
        $restricted = $this->user('sales', ['leads.view_own', 'leads.manage']);
        $mine = $this->lead($restricted);
        $other = $this->lead($this->user('sales'));

        $this->actingAs($restricted)->get('/panel/leads')
            ->assertOk()
            ->assertSee($mine->name)
            ->assertDontSee($other->email);

        $this->actingAs($restricted)->get("/panel/leads/{$mine->id}")->assertOk();
        $this->actingAs($restricted)->get("/panel/leads/{$other->id}")->assertForbidden();
        $this->actingAs($restricted)->delete("/panel/leads/{$other->id}")->assertForbidden();
    }

    /** Los permisos marcados a mano mandan sobre los del rol. */
    public function test_explicit_permissions_replace_the_role_defaults(): void
    {
        $readOnly = $this->user('sales', ['leads.view_all']);
        $lead = $this->lead();

        $this->actingAs($readOnly)->get('/panel/leads')->assertOk();
        $this->actingAs($readOnly)->get("/panel/leads/{$lead->id}")->assertOk();
        $this->actingAs($readOnly)->get('/panel/leads/create')->assertForbidden();
        $this->actingAs($readOnly)->delete("/panel/leads/{$lead->id}")->assertForbidden();
        $this->actingAs($readOnly)->get('/panel/quotes')->assertForbidden();
    }

    /** La infraestructura del cliente (hosting, dominios, DNS) exige clients.manage. */
    public function test_client_infrastructure_requires_manage(): void
    {
        $viewer = $this->user('sales', ['clients.view']);
        $client = $this->client();

        $this->actingAs($viewer)->get('/panel/clients')->assertOk();
        $this->actingAs($viewer)->get("/panel/clients/{$client->id}")->assertOk();
        $this->actingAs($viewer)->get('/panel/domains')->assertForbidden();
        $this->actingAs($viewer)->get("/panel/clients/{$client->id}/mailboxes")->assertForbidden();
        $this->actingAs($viewer)->post("/panel/clients/{$client->id}/create-hosting")->assertForbidden();
    }

    public function test_an_anonymous_visitor_is_sent_to_login(): void
    {
        $this->get('/panel/leads')->assertRedirect('/login');
        $this->get('/panel/quotes')->assertRedirect('/login');
    }

    // ── Menú lateral ─────────────────────────────────────

    /** @return list<string> rutas ofrecidas en el menú lateral */
    private function menuPaths(User $user): array
    {
        return $this->actingAs($user)->app->call(function () {
            return collect(MenuHelper::getMenuGroups())
                ->flatMap(fn ($group) => $group['items'])
                ->pluck('path')
                ->all();
        });
    }

    /** El menú no debe ofrecer secciones que responden 403. */
    public function test_the_sidebar_only_offers_reachable_sections(): void
    {
        $paths = $this->menuPaths($this->user('accounting'));

        $this->assertNotContains('/panel/leads', $paths);
        $this->assertContains('/panel/orders', $paths);
        $this->assertContains('/panel/reports', $paths);
        $this->assertContains('/panel/quotes', $paths);
    }

    public function test_a_restricted_user_sees_a_reduced_sidebar(): void
    {
        $paths = $this->menuPaths($this->user('sales', ['leads.view_all']));

        $this->assertContains('/panel/leads', $paths);
        $this->assertNotContains('/panel/quotes', $paths);
        $this->assertNotContains('/panel/orders', $paths);
    }

    /** Facturación pasó de rol a permiso: contabilidad debe conservarla. */
    public function test_accounting_keeps_invoicing_after_the_switch_to_permissions(): void
    {
        $accounting = $this->user('accounting');

        $this->actingAs($accounting)->get('/panel/orders')->assertOk();
        $this->actingAs($accounting)->get('/panel/reports')->assertOk();
        $this->actingAs($accounting)->get('/panel/recurring-invoices')->assertOk();
    }

    public function test_sales_cannot_reach_invoicing(): void
    {
        $sales = $this->user('sales');

        $this->actingAs($sales)->get('/panel/orders')->assertForbidden();
        $this->actingAs($sales)->get('/panel/reports')->assertForbidden();
    }
}
