<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Setting;
use App\Services\TwentyIService;
use Illuminate\Http\Request;

class MailboxController extends Controller
{
    public function index(Client $client)
    {
        $mailboxes = [];
        $error     = null;
        $domain    = null;

        if ($client->twentyi_package_id && Setting::get('twentyi_api_key')) {
            try {
                $service   = new TwentyIService();
                $domain    = $service->getDomain($client);
                $mailboxes = $service->listMailboxes($client);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('admin.mailboxes.index', compact('client', 'mailboxes', 'error', 'domain'));
    }

    public function store(Request $request, Client $client)
    {
        $validated = $request->validate([
            'local'    => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9._+-]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'quota_mb' => ['nullable', 'integer', 'min:100', 'max:51200'],
        ]);

        if (!$client->twentyi_package_id) {
            return back()->with('error', 'Este cliente no tiene un paquete 20i configurado.');
        }

        if (!Setting::get('twentyi_api_key')) {
            return back()->with('error', 'Falta configurar la API Key de 20i en Ajustes.');
        }

        try {
            (new TwentyIService())->createMailbox(
                $client,
                $validated['local'],
                $validated['password'],
                (int) ($validated['quota_mb'] ?? 10240)
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo crear el buzón: ' . $e->getMessage());
        }

        return redirect()->route('admin.clients.mailboxes.index', $client)
            ->with('success', "Buzón {$validated['local']} creado correctamente.");
    }

    public function destroy(Client $client, string $mailboxId)
    {
        if (!$client->twentyi_package_id || !Setting::get('twentyi_api_key')) {
            return back()->with('error', 'Configuración incompleta.');
        }

        try {
            (new TwentyIService())->deleteMailbox($client, $mailboxId);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo eliminar el buzón: ' . $e->getMessage());
        }

        return redirect()->route('admin.clients.mailboxes.index', $client)
            ->with('success', 'Buzón eliminado.');
    }

    public function changePassword(Request $request, Client $client, string $mailboxId)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        if (!$client->twentyi_package_id || !Setting::get('twentyi_api_key')) {
            return back()->with('error', 'Configuración incompleta.');
        }

        try {
            (new TwentyIService())->updateMailboxPassword($client, $mailboxId, $validated['password']);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo cambiar la contraseña: ' . $e->getMessage());
        }

        return back()->with('success', 'Contraseña actualizada.');
    }

    public function webmail(Client $client, string $mailboxId)
    {
        if (!$client->twentyi_package_id || !Setting::get('twentyi_api_key')) {
            return back()->with('error', 'Configuración incompleta.');
        }

        try {
            $url = (new TwentyIService())->getWebmailUrl($client, $mailboxId);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo obtener el enlace webmail: ' . $e->getMessage());
        }

        if (!$url) {
            return back()->with('error', '20i no devolvió una URL de webmail.');
        }

        return redirect()->away($url);
    }
}
