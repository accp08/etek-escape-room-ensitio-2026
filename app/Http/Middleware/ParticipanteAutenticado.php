<?php

namespace App\Http\Middleware;

use App\Models\Participante;
use App\Support\Bitacora;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sesión del participante del juego, independiente de la del dashboard.
 *
 * El acceso se valida solo con el correo del participante, cargado
 * previamente por el administrador (o con Outlook si así se configura).
 */
class ParticipanteAutenticado
{
    public const CLAVE_SESION = 'participante_id';

    /** Token de la sesión: debe coincidir con el último inicio de sesión del participante. */
    public const CLAVE_TOKEN = 'participante_token';

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
            self::cerrar($peticion);

            // Se recuerda la URL pedida: quien escanea el QR de una estación
            // sin sesión vuelve a esa misión después de entrar.
            return redirect()->guest(route('login'));
        }

        // Una sola sesión por participante: si entró desde otro dispositivo,
        // esta sesión ya no vale y se cierra.
        if (! self::sesionVigente($peticion, $participante)) {
            Bitacora::juego('sesion.reemplazada', "Se cerró una sesión de {$participante->nombre}: entró desde otro dispositivo", [
                'ruta' => $peticion->route()?->getName(),
            ], 'aviso', $participante->id);

            self::cerrar($peticion);

            return redirect()->guest(route('login'))->withErrors([
                'correo' => 'Tu sesión se abrió en otro dispositivo. Vuelve a entrar para seguir jugando aquí.',
            ]);
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

    /**
     * Abre la sesión del participante en esta petición y cierra cualquier
     * otra que tuviera abierta en otro dispositivo.
     */
    public static function iniciar(Request $peticion, Participante $participante): void
    {
        $token = Str::random(64);

        $peticion->session()->regenerate();
        $peticion->session()->put(self::CLAVE_SESION, $participante->id);
        $peticion->session()->put(self::CLAVE_TOKEN, $token);

        Participante::withoutTimestamps(fn () => $participante->forceFill(['sesion_token' => $token])->saveQuietly());
    }

    public static function cerrar(Request $peticion): void
    {
        $peticion->session()->forget([self::CLAVE_SESION, self::CLAVE_TOKEN]);
    }

    /**
     * La sesión vale si su token es el del último inicio de sesión. Un
     * participante sin token (sesiones abiertas antes de este control) se
     * acepta hasta que vuelva a entrar.
     */
    private static function sesionVigente(Request $peticion, Participante $participante): bool
    {
        if ($participante->sesion_token === null) {
            return true;
        }

        $token = $peticion->session()->get(self::CLAVE_TOKEN);

        return is_string($token) && hash_equals($participante->sesion_token, $token);
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
