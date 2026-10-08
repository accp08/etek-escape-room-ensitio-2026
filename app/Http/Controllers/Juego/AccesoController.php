<?php

namespace App\Http\Controllers\Juego;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ParticipanteAutenticado;
use App\Models\Participante;
use App\Services\Juego;
use App\Support\Bitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccesoController extends Controller
{
    public function formulario(Request $peticion)
    {
        if ($peticion->session()->has(ParticipanteAutenticado::CLAVE_SESION)) {
            return redirect()->route('hub');
        }

        return view('auth.login');
    }

    public function entrar(Request $peticion, Juego $juego): RedirectResponse
    {
        // Usuario: correo. Contraseña: número de cédula.
        $datos = $peticion->validate([
            'correo' => ['required', 'string', 'max:160'],
            'documento' => ['required', 'string', 'max:40'],
        ], [], [
            'correo' => 'correo',
            'documento' => 'contraseña',
        ]);

        $participante = Participante::where('activo', true)
            ->where('correo', mb_strtolower(trim($datos['correo'])))
            ->first();

        if (! $participante || ! $participante->documentoCoincide($datos['documento'])) {
            Bitacora::juego('acceso.fallido', 'Intento de acceso fallido', [
                'correo' => $datos['correo'],
            ], 'aviso');

            throw ValidationException::withMessages([
                'correo' => 'El correo o la contraseña no son correctos. Recuerda: la contraseña es tu número de cédula.',
            ]);
        }

        $peticion->session()->regenerate();
        $peticion->session()->put(ParticipanteAutenticado::CLAVE_SESION, $participante->id);

        // La partida se crea (o se retoma) al entrar.
        $partida = $juego->partidaDe($participante);

        Bitacora::juego('acceso', "{$participante->nombre} entró al juego", [
            'partida_id' => $partida->id,
        ]);

        // Si llegó escaneando el QR de una estación, vuelve a esa misión. Solo se
        // honran URLs de misión: la misma clave de sesión la usa el dashboard.
        $pedida = $peticion->session()->pull('url.intended');

        if ($pedida && str_contains((string) parse_url($pedida, PHP_URL_PATH), '/mision/')) {
            return redirect()->to($pedida);
        }

        return redirect()->route(
            $partida->misiones()->where('estado', '!=', 'pendiente')->exists() ? 'hub' : 'instrucciones'
        );
    }

    public function salir(Request $peticion): RedirectResponse
    {
        Bitacora::juego('salida', 'El participante cerró su sesión');

        $peticion->session()->forget(ParticipanteAutenticado::CLAVE_SESION);
        $peticion->session()->regenerateToken();

        return redirect()->route('login');
    }
}
