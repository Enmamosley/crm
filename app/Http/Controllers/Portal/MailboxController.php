<?php

namespace App\Http\Controllers\Portal;

use App\Models\Setting;
use App\Services\TwentyIService;
use Illuminate\Http\Request;

/**
 * Buzones de correo del cliente (API de 20i). Requiere un servicio de correo contratado.
 */

class MailboxController extends PortalController
{
    public function __construct(
        private TwentyIService $twentyi,
    ) {}

    public function mailboxes(string $token)
    {
        $client = $this->client();

        $this->requireEmailService($client);

        $mailboxes = [];
        $domain = null;
        $error = null;

        if ($client->twentyi_package_id && Setting::get('twentyi_api_key')) {
            try {
                $service   = $this->twentyi;
                $domain    = $service->getDomain($client);
                $mailboxes = $service->listMailboxes($client);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('portal.mailboxes', compact('client', 'mailboxes', 'domain', 'error'));
    }

    public function storeMailbox(Request $request, string $token)
    {
        $client = $this->client();

        $this->requireEmailService($client);

        $validated = $request->validate([
            'local'    => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9._+-]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        if (!$client->twentyi_package_id || !Setting::get('twentyi_api_key')) {
            return back()->with('error', 'Servicio de correo no configurado.');
        }

        try {
            $this->twentyi->createMailbox($client, $validated['local'], $validated['password']);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo crear el buzón: ' . $e->getMessage());
        }

        return redirect()->route('portal.mailboxes', $client->portal_token)
            ->with('success', "Buzón {$validated['local']} creado correctamente.");
    }

    public function destroyMailbox(string $token, string $mailbox)
    {
        $client = $this->client();

        $this->requireEmailService($client);

        if (!$client->twentyi_package_id || !Setting::get('twentyi_api_key')) {
            return back()->with('error', 'Servicio de correo no configurado.');
        }

        try {
            $this->twentyi->deleteMailbox($client, $mailbox);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo eliminar el buzón: ' . $e->getMessage());
        }

        return redirect()->route('portal.mailboxes', $client->portal_token)
            ->with('success', 'Buzón eliminado.');
    }

    public function changeMailboxPassword(Request $request, string $token, string $mailbox)
    {
        $client = $this->client();

        $this->requireEmailService($client);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        if (!$client->twentyi_package_id || !Setting::get('twentyi_api_key')) {
            return back()->with('error', 'Servicio de correo no configurado.');
        }

        try {
            $this->twentyi->updateMailboxPassword($client, $mailbox, $validated['password']);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo cambiar la contraseña: ' . $e->getMessage());
        }

        return back()->with('success', 'Contraseña actualizada.');
    }

    public function webmail(string $token, string $mailbox)
    {
        $client = $this->client();

        $this->requireEmailService($client);

        if (!$client->twentyi_package_id || !Setting::get('twentyi_api_key')) {
            abort(404);
        }

        $url = $this->twentyi->getWebmailUrl($client, $mailbox);

        if (!$url) {
            return back()->with('error', 'No se pudo obtener el enlace de webmail.');
        }

        return redirect()->away($url);
    }
}
