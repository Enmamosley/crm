<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    /**
     * Solicitar un magic link. Recibe el email, genera token y lo envía.
     * POST /auth/magic
     */
    public function send(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($request->input('email')));

        // ── Cliente con portal activo → enviar URL de portal directamente ──
        $client = Client::where('email', $email)->where('portal_active', true)->first();
        if ($client) {
            Mail::to($email)->send(
                new MagicLinkMail($client->portalUrl(), 'Ir a mi portal →')
            );
            return back()->with('magic_sent', true);
        }

        // ── Admin user → magic link temporal (15 min) ──────────────────────
        if (User::where('email', $email)->exists()) {
            // Limpiar tokens anteriores para este email
            DB::table('magic_link_tokens')->where('email', $email)->delete();

            $token = Str::random(64);

            DB::table('magic_link_tokens')->insert([
                'email'      => $email,
                'token'      => $token,
                'expires_at' => now()->addMinutes(15),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Mail::to($email)->send(new MagicLinkMail(
                route('auth.magic.verify', ['token' => $token]),
                'Entrar al panel →'
            ));
        }

        return back()->with('magic_sent', true);
    }

    /**
     * Verificar el token del magic link y autenticar al usuario.
     * GET /auth/magic/{token}
     */
    public function verify(string $token)
    {
        $record = DB::table('magic_link_tokens')
            ->where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return redirect('/login')->withErrors(['email' => 'El enlace es inválido o ya expiró.']);
        }

        $user = User::where('email', $record->email)->first();

        if (! $user) {
            return redirect('/login')->withErrors(['email' => 'No se encontró una cuenta con ese correo.']);
        }

        // Marcar como usado
        DB::table('magic_link_tokens')->where('token', $token)->update(['used_at' => now()]);

        Auth::login($user, remember: true);
        session()->regenerate();

        return redirect()->intended('/panel');
    }
}
