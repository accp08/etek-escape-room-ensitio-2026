<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Support\Bitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SesionController extends Controller
{
    public function formulario()
    {
        return Auth::check()
            ? redirect()->route('dashboard')
            : view('dashboard.login');
    }

    public function entrar(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], [
            'email' => 'correo',
            'password' => 'contraseña',
        ]);

        if (! Auth::attempt($datos, $peticion->boolean('recordarme'))) {
            Bitacora::sistema('dashboard.acceso_fallido',
                "Intento de acceso fallido: {$datos['email']}", [], 'alerta');

            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        $peticion->session()->regenerate();

        Bitacora::dashboard('acceso', Auth::user()->name.' entró al dashboard');

        return redirect()->intended(route('dashboard'));
    }

    public function salir(Request $peticion): RedirectResponse
    {
        Bitacora::dashboard('salida', Auth::user()?->name.' cerró sesión');

        Auth::logout();
        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        return redirect()->route('dashboard.login');
    }
}
