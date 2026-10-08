<?php

namespace App\Support;

use App\Models\Ajuste;
use App\Models\Texto;
use Throwable;

/**
 * Acceso al contenido editable (textos y ajustes).
 * Todo se lee una sola vez por petición.
 *
 * Si las tablas aún no existen —por ejemplo antes de migrar— devuelve los
 * valores por defecto, así la aplicación nunca se cae por contenido faltante.
 */
class Contenido
{
    private static ?array $textos = null;

    private static ?array $ajustes = null;

    public static function texto(string $clave, string $porDefecto = ''): string
    {
        if (self::$textos === null) {
            self::$textos = self::cargar(fn () => Texto::pluck('valor', 'clave')->all());
        }

        return self::$textos[$clave] ?? $porDefecto;
    }

    public static function ajuste(string $clave, mixed $porDefecto = null): mixed
    {
        if (self::$ajustes === null) {
            self::$ajustes = self::cargar(fn () => Ajuste::pluck('valor', 'clave')->all());
        }

        $valor = self::$ajustes[$clave] ?? null;

        if ($valor === null) {
            return $porDefecto;
        }

        return is_numeric($valor) ? $valor + 0 : $valor;
    }

    public static function olvidar(): void
    {
        self::$textos = self::$ajustes = null;
    }

    private static function cargar(callable $consulta): array
    {
        try {
            return $consulta();
        } catch (Throwable) {
            return [];
        }
    }
}
