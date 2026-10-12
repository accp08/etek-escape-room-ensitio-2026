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

    /**
     * Pantallas que quedan abiertas cuando el código final ya se validó en la
     * consola: el juego terminó y solo queda el certificado, el video y el ranking.
     */
    public const RUTAS_TERMINADO = [
        'victoria', 'certificado', 'certificado.descargado', 'video.final', 'ranking',
        'presencia', 'final.estado',
    ];

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

        $this->registrarPresencia($peticion, $participante);

        if (! in_array($peticion->route()?->getName(), self::RUTAS_TERMINADO, true)
            && self::terminoElJuego($participante)) {
            return redirect()->route('certificado');
        }

        return $siguiente($peticion);
    }

    /** Ya digitó su código de recuperación válido en la consola. */
    public static function terminoElJuego(Participante $participante): bool
    {
        return (bool) $participante->partidaActual()?->codigoIngresado();
    }

    /**
     * Anota cuándo se vio al participante y en qué pantalla está, para
     * «Jugadores en línea» del dashboard. Para no escribir en cada clic, solo
     * guarda si cambió de pantalla o si el último registro ya tiene unos segundos.
     */
    private function registrarPresencia(Request $peticion, Participante $participante): void
    {
        $ubicacion = self::ubicacionDe($peticion) ?? $participante->ubicacion;

        $reciente = $participante->visto_en?->gt(now()->subSeconds(20)) ?? false;

        if ($reciente && $ubicacion === $participante->ubicacion) {
            return;
        }

        Participante::withoutTimestamps(fn () => $participante->forceFill([
            'visto_en' => now(),
            'ubicacion' => $ubicacion,
        ])->saveQuietly());
    }

    /**
     * Pantalla del juego según la ruta: «hub», «final», «mision:phishing»…
     * Null para las peticiones de fondo (latido, sondeos), que no cambian de pantalla.
     */
    private static function ubicacionDe(Request $peticion): ?string
    {
        $ruta = (string) $peticion->route()?->getName();

        return match (true) {
            str_starts_with($ruta, 'mision') => 'mision:'.$peticion->route('slug'),
            in_array($ruta, ['presencia', 'final.estado', 'certificado.descargado'], true) => null,
            $ruta === '' => null,
            default => $ruta,
        };
    }
}
