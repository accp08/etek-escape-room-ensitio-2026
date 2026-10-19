<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ninguna página con sesión se guarda en caché: ni en el navegador, ni en el
 * service worker, ni en un proxy del hosting. Una respuesta cacheada con la
 * cookie de sesión de un jugador le entregaría esa sesión a otro.
 *
 * Los archivos que se declaran públicos a propósito (video, audios, logo)
 * conservan su Cache-Control.
 */
class SinCache
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $respuesta = $siguiente($peticion);

        if (! str_contains((string) $respuesta->headers->get('Cache-Control'), 'public')) {
            $respuesta->headers->set('Cache-Control', 'no-store, private, max-age=0');
            $respuesta->headers->set('Pragma', 'no-cache');
        }

        return $respuesta;
    }
}
