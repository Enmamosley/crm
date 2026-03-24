<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            ActivityLog::create([
                'user_id'      => Auth::id(),
                'action'       => 'login',
                'subject_type' => 'App\\Models\\User',
                'subject_id'   => Auth::id(),
                'description'  => 'Inicio de sesión: ' . Auth::user()->name,
                'ip_address'   => $request->ip(),
            ]);

            return redirect()->intended('/admin');
        }

        ActivityLog::create([
            'action'       => 'login_failed',
            'subject_type' => '',
            'description'  => 'Intento fallido de login: ' . $request->input('email'),
            'ip_address'   => $request->ip(),
        ]);

        return back()->withErrors(['email' => 'Credenciales incorrectas.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        $userName = Auth::user()?->name;
        $userId = Auth::id();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($userId) {
            ActivityLog::create([
                'user_id'      => $userId,
                'action'       => 'logout',
                'subject_type' => 'App\\Models\\User',
                'subject_id'   => $userId,
                'description'  => 'Cierre de sesión: ' . $userName,
                'ip_address'   => $request->ip(),
            ]);
        }

        return redirect('/login');
    }
}
