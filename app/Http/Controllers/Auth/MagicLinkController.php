<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
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

        // Siempre responder igual para no filtrar qué emails existen
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

            Mail::to($email)->send(new MagicLinkMail($token));
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
