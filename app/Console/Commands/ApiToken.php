<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\ApiAbilities;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Emite, lista y revoca los tokens de la API.
 *
 * Antes el único sitio donde nacía un token era el seeder, y lo hacía con
 * `createToken('openclaw-agent')`: sin permisos —comodín `*` para Sanctum— y
 * colgando del administrador. Rotarlo obligaba a repetir esa misma llamada a
 * mano en tinker, con el mismo resultado. Este comando lo emite acotado y con
 * caducidad, y permite retirar el anterior.
 */
class ApiToken extends Command
{
    protected $signature = 'api:token
        {name? : Nombre con el que se identifica el token}
        {--abilities= : Permisos separados por coma; por omisión, los del agente OpenClaw}
        {--days=30 : Días de vigencia}
        {--list : Lista los tokens vigentes en lugar de emitir uno}
        {--revoke= : ID del token a revocar}';

    protected $description = 'Emite, lista o revoca tokens de la API acotados por permisos';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listTokens();
        }

        if ($id = $this->option('revoke')) {
            return $this->revoke((int) $id);
        }

        return $this->mint();
    }

    private function mint(): int
    {
        $name = $this->argument('name') ?: 'openclaw-agent';

        $abilities = $this->option('abilities')
            ? array_values(array_filter(array_map('trim', explode(',', $this->option('abilities')))))
            : ApiAbilities::OPENCLAW;

        // Un permiso mal escrito no concede nada y no se nota hasta que el
        // agente falla en producción: mejor no emitir el token.
        if ($unknown = ApiAbilities::unknown($abilities)) {
            $this->error('Permisos que no existen: ' . implode(', ', $unknown));
            $this->line('Disponibles: ' . implode(', ', ApiAbilities::names()));

            return self::FAILURE;
        }

        if (!$abilities) {
            $this->error('Un token sin permisos no sirve para nada. Indica al menos uno con --abilities.');

            return self::FAILURE;
        }

        $days  = max(1, (int) $this->option('days'));
        $token = User::apiAgent()->createToken($name, $abilities, now()->addDays($days));

        $this->info("Token «{$name}» emitido.");
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->line('Permisos: ' . implode(', ', $abilities));
        $this->line('Caduca:   ' . $token->accessToken->expires_at->format('d/m/Y'));
        $this->warn('Guárdalo ahora: no vuelve a mostrarse.');

        return self::SUCCESS;
    }

    private function listTokens(): int
    {
        $tokens = PersonalAccessToken::orderBy('id')->get();

        if ($tokens->isEmpty()) {
            $this->info('No hay tokens emitidos.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Nombre', 'Permisos', 'Caduca', 'Último uso'],
            $tokens->map(fn (PersonalAccessToken $token) => [
                $token->id,
                $token->name,
                in_array('*', $token->abilities, true) ? 'TODOS (*) — conviene reemitirlo' : implode(', ', $token->abilities),
                $token->expires_at?->format('d/m/Y') ?? 'según sanctum.expiration',
                $token->last_used_at?->format('d/m/Y H:i') ?? 'nunca',
            ]),
        );

        return self::SUCCESS;
    }

    private function revoke(int $id): int
    {
        $token = PersonalAccessToken::find($id);

        if (!$token) {
            $this->error("No existe ningún token con ID {$id}.");

            return self::FAILURE;
        }

        $name = $token->name;
        $token->delete();
        $this->info("Token «{$name}» revocado.");

        return self::SUCCESS;
    }
}
