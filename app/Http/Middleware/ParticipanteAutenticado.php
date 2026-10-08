<?php

namespace App\Http\Middleware;

use App\Models\Participante;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sesión del participante del juego, independiente de la del dashboard.
 *
 * El acceso se valida con documento + código entregado por el administrador.
 * No es una credencial sensible (es un código de juego de un solo evento),
 * por eso se guarda en claro y el administrador puede consultarlo.
 */
class ParticipanteAutenticado
{
    public const CLAVE_SESION = 'participante_id';

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $id = $peticion->session()->get(self::CLAVE_SESION);

        $participante = $id ? Participante::where('activo', true)->find($id) : null;

        if (! $participante) {
            $peticion->session()->forget(self::CLAVE_SESION);

            // Se recuerda la URL pedida: quien escanea el QR de una estación
            // sin sesión vuelve a esa misión después de entrar.
            return redirect()->guest(route('login'));
        }

        // Disponible como $peticion->participante() en los controladores.
        $peticion->attributes->set('participante', $participante);

        return $siguiente($peticion);
    }
}
