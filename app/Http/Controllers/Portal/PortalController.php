<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de los controladores del portal del cliente.
 *
 * El cliente ya lo resuelve el middleware ValidatePortalToken a partir del
 * token de la URL, así que aquí sólo se recoge. Antes cada método repetía la
 * consulta, con tres variantes distintas y una comprobación de pertenencia
 * copiada diecisiete veces.
 */
abstract class PortalController extends Controller
{
    /** El cliente dueño del portal, resuelto por el middleware. */
    protected function client(): Client
    {
        $client = request()->attributes->get('portal_client');

        abort_unless($client instanceof Client, 404);

        return $client;
    }

    /** Un cliente sólo puede ver lo suyo. */
    protected function authorizeOwnership(Client $client, Model $model): void
    {
        abort_if($model->client_id !== $client->id, 403);
    }

    /** Las cotizaciones cuelgan del lead, no del cliente. */
    protected function authorizeQuote(Client $client, Quote $quote): void
    {
        abort_if(!$client->lead || $quote->lead_id !== $client->lead->id, 403);
    }

    /** La gestión de correos exige un servicio de correo contratado. */
    protected function requireEmailService(Client $client): void
    {
        abort_unless($client->hasEmailService(), 403, 'No tienes un servicio de correo profesional contratado.');
    }

    /** ¿El cliente tiene hosting en 20i y la API está configurada? */
    protected function twentyiReady(Client $client): bool
    {
        return (bool) ($client->twentyi_package_id && Setting::get('twentyi_api_key'));
    }
}
