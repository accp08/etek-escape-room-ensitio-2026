<?php

namespace App\Support;

use App\Http\Middleware\ParticipanteAutenticado;
use App\Models\Participante;
use App\Models\Registro;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Registra actividad en la bitácora.
 *
 * Nunca debe tumbar la operación que la invoca: si el registro falla (por
 * ejemplo antes de migrar), se descarta en silencio.
 */
class Bitacora
{
    public static function dashboard(string $tipo, string $descripcion, array $contexto = [], string $nivel = 'info'): void
    {
        self::escribir('dashboard', $tipo, $descripcion, $contexto, $nivel, Auth::id());
    }

    public static function juego(string $tipo, string $descripcion, array $contexto = [], string $nivel = 'info'): void
    {
        self::escribir('juego', $tipo, $descripcion, $contexto, $nivel, null, self::participanteActual());
    }

    public static function sistema(string $tipo, string $descripcion, array $contexto = [], string $nivel = 'aviso'): void
    {
        self::escribir('sistema', $tipo, $descripcion, $contexto, $nivel);
    }

    private static function escribir(
        string $canal,
        string $tipo,
        string $descripcion,
        array $contexto,
        string $nivel,
        ?int $usuarioId = null,
        ?int $participanteId = null,
    ): void {
        try {
            Registro::create([
                'canal' => $canal,
                'tipo' => $tipo,
                'nivel' => $nivel,
                'descripcion' => mb_substr($descripcion, 0, 255),
                'user_id' => $usuarioId,
                'participante_id' => $participanteId,
                'contexto' => $contexto ?: null,
                'ip' => request()?->ip(),
            ]);
        } catch (Throwable) {
            // La bitácora no interrumpe el flujo.
        }
    }

    private static function participanteActual(): ?int
    {
        $id = session(ParticipanteAutenticado::CLAVE_SESION);

        return $id && Participante::whereKey($id)->exists() ? (int) $id : null;
    }
}
