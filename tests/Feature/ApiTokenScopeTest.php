<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Support\ApiAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El token del agente se emitía con `createToken('openclaw-agent')` a secas:
 * sin lista de permisos, que en Sanctum significa comodín `*`, y sobre el
 * usuario administrador. Un token filtrado abría todo el API — y, peor,
 * cualquier endpoint que se añadiera después, sin volver a tocarlo.
 */
class ApiTokenScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    private function user(string $role = 'agent'): User
    {
        return User::create([
            'name' => 'Agente API', 'email' => uniqid() . '@test.com',
            'password' => bcrypt('secret'), 'role' => $role,
        ]);
    }

    /** @param list<string> $abilities */
    private function tokenFor(array $abilities, ?User $user = null): string
    {
        return ($user ?? $this->user())->createToken('prueba', $abilities)->plainTextToken;
    }

    private function callWith(string $token, string $method, string $uri, array $body = [])
    {
        return $this->json($method, $uri, $body, ['Authorization' => "Bearer {$token}"]);
    }

    // ── El alcance se respeta ────────────────────────────

    public function test_a_read_only_token_cannot_write_leads(): void
    {
        $token = $this->tokenFor(['leads:read']);
        Lead::create(['name' => 'Prospecto', 'email' => 'p@test.com', 'status' => 'nuevo']);

        $this->callWith($token, 'GET', '/api/v1/leads')->assertOk();

        $this->callWith($token, 'POST', '/api/v1/leads', ['name' => 'Nuevo', 'phone' => '5512345678'])
            ->assertForbidden();
        $this->assertSame(1, Lead::count());
    }

    public function test_a_leads_token_cannot_reach_quotes_or_settings(): void
    {
        $token = $this->tokenFor(['leads:read', 'leads:write']);

        $this->callWith($token, 'POST', '/api/v1/quotes', [])->assertForbidden();
        $this->callWith($token, 'GET', '/api/v1/settings')->assertForbidden();
        $this->callWith($token, 'GET', '/api/v1/agent/status')->assertForbidden();
    }

    public function test_a_scoped_token_reaches_what_it_was_given(): void
    {
        $token = $this->tokenFor(['settings:read', 'agent:read']);

        $this->callWith($token, 'GET', '/api/v1/settings')->assertOk();
        $this->callWith($token, 'GET', '/api/v1/agent/status')->assertOk();
    }

    /** El agente sigue funcionando con el juego completo de permisos. */
    public function test_the_openclaw_set_covers_the_whole_documented_api(): void
    {
        $token = $this->tokenFor(ApiAbilities::OPENCLAW);
        $lead = Lead::create(['name' => 'Prospecto', 'email' => 'p@test.com', 'status' => 'nuevo']);

        $this->callWith($token, 'GET', '/api/v1/leads')->assertOk();
        $this->callWith($token, 'GET', '/api/v1/leads/search?name=Prospecto')->assertOk();
        $this->callWith($token, 'GET', "/api/v1/leads/{$lead->id}")->assertOk();
        $this->callWith($token, 'PATCH', "/api/v1/leads/{$lead->id}/status", ['status' => 'contactado'])->assertOk();
        $this->callWith($token, 'POST', "/api/v1/leads/{$lead->id}/notes", ['content' => 'Nota'])->assertSuccessful();
        $this->callWith($token, 'GET', '/api/v1/settings')->assertOk();
        $this->callWith($token, 'GET', '/api/v1/agent/status')->assertOk();
    }

    // ── Lo que se emite ──────────────────────────────────

    /** Los tokens nuevos nacen acotados y con fecha de caducidad. */
    public function test_minted_tokens_are_scoped_and_expire(): void
    {
        $this->artisan('api:token', ['name' => 'openclaw', '--days' => 30])->assertSuccessful();

        $token = \Laravel\Sanctum\PersonalAccessToken::firstOrFail();

        $this->assertNotContains('*', $token->abilities);
        $this->assertSame(ApiAbilities::OPENCLAW, $token->abilities);
        $this->assertNotNull($token->expires_at);
        $this->assertFalse($token->can('cualquier-cosa'));
    }

    /** El token no lo lleva un administrador. */
    public function test_the_api_identity_is_not_an_admin(): void
    {
        $this->artisan('api:token', ['name' => 'openclaw'])->assertSuccessful();

        $owner = \Laravel\Sanctum\PersonalAccessToken::firstOrFail()->tokenable;

        $this->assertFalse($owner->isAdmin());
        $this->assertSame([], $owner->effectivePermissions());
    }

    public function test_an_unknown_ability_is_refused(): void
    {
        $this->artisan('api:token', ['name' => 'malo', '--abilities' => 'leads:read,inventado'])
            ->expectsOutputToContain('inventado')
            ->assertFailed();

        $this->assertSame(0, \Laravel\Sanctum\PersonalAccessToken::count());
    }

    // ── Caducidad ────────────────────────────────────────

    /** Regresión: `sanctum.expiration` está en 30 días y debe seguir aplicándose. */
    public function test_an_expired_token_is_rejected(): void
    {
        $token = $this->tokenFor(['settings:read']);

        $this->travel(31)->days();

        $this->callWith($token, 'GET', '/api/v1/settings')->assertUnauthorized();
    }

    // ── Compatibilidad con lo ya emitido ─────────────────

    /**
     * El token que hoy está en producción lleva comodín. Sanctum lo da por
     * bueno ante cualquier permiso, así que exigirlos no rompe el agente: sigue
     * funcionando hasta que se reemita.
     */
    public function test_an_existing_wildcard_token_keeps_working(): void
    {
        $token = $this->tokenFor(['*'], $this->user('admin'));

        $this->callWith($token, 'GET', '/api/v1/settings')->assertOk();
        $this->callWith($token, 'POST', '/api/v1/leads', ['name' => 'Nuevo', 'phone' => '5512345678'])
            ->assertSuccessful();
    }

    /** Y se ve en el listado que hay que reemitirlo. */
    public function test_the_listing_flags_a_wildcard_token(): void
    {
        $this->tokenFor(['*'], $this->user('admin'));

        $this->artisan('api:token', ['--list' => true])
            ->expectsOutputToContain('TODOS (*)')
            ->assertSuccessful();
    }

    public function test_a_token_can_be_revoked(): void
    {
        $this->artisan('api:token', ['name' => 'viejo'])->assertSuccessful();
        $id = \Laravel\Sanctum\PersonalAccessToken::firstOrFail()->id;

        $this->artisan('api:token', ['--revoke' => $id])->assertSuccessful();

        $this->assertSame(0, \Laravel\Sanctum\PersonalAccessToken::count());
    }
}
