<?php

namespace App\Support;

use App\Models\Empresa;
use Throwable;

/**
 * Resuelve la marca que se muestra en el juego.
 *
 * Prioridad: la empresa activa guardada desde el dashboard; si no hay ninguna
 * (o la tabla todavía no existe), se cae a los valores de config/escape_room.php.
 * Así el juego nunca queda sin marca, incluso antes de migrar.
 */
class Marca
{
    private static ?array $cache = null;

    /** @return array{nombre: string, logo: ?string, empresa: ?Empresa} */
    public static function actual(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $empresa = self::empresaActiva();

        return self::$cache = [
            'nombre' => $empresa?->nombre ?: config('escape_room.marca.cliente'),
            'logo' => $empresa?->logoUrl() ?: self::logoPorDefecto(),
            'empresa' => $empresa,
        ];
    }

    public static function empresaActiva(): ?Empresa
    {
        try {
            return Empresa::where('activa', true)->latest('id')->first();
        } catch (Throwable) {
            // La tabla aún no existe (antes de migrar): se usa el config.
            return null;
        }
    }

    /** Limpia la caché en memoria tras guardar cambios. */
    public static function olvidar(): void
    {
        self::$cache = null;
    }

    private static function logoPorDefecto(): ?string
    {
        $ruta = config('escape_room.marca.logo');

        return $ruta ? asset($ruta) : null;
    }
}
