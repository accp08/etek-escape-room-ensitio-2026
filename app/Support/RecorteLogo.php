<?php

namespace App\Support;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Quita el margen vacío alrededor de un logo (fondo blanco, transparente o de
 * cualquier color uniforme). Así el logo llena la placa blanca en vez de
 * quedar como un punto en medio de mucho espacio. Los SVG no se tocan.
 */
final class RecorteLogo
{
    /** Diferencia máxima (0–255 por canal) para considerar un píxel parte del fondo. */
    private const TOLERANCIA = 24;

    /** Recorta el archivo del disco público en su lugar. Devuelve true si lo cambió. */
    public static function recortar(string $ruta): bool
    {
        $disco = Storage::disk('public');
        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true) || ! $disco->exists($ruta)) {
            return false;
        }

        try {
            $imagen = @imagecreatefromstring($disco->get($ruta));

            if (! $imagen) {
                return false;
            }

            $caja = self::cajaDelContenido($imagen);

            if ($caja === null) {
                return false;
            }

            $recortada = imagecrop($imagen, $caja);
            imagesavealpha($recortada, true);

            ob_start();
            match ($extension) {
                'png' => imagepng($recortada, null, 9),
                'webp' => imagewebp($recortada, null, 92),
                default => imagejpeg($recortada, null, 92),
            };
            $disco->put($ruta, (string) ob_get_clean());

            return true;
        } catch (Throwable) {
            return false;   // si algo falla, se queda el logo tal como se subió
        }
    }

    /**
     * Rectángulo que ocupa el logo dentro de la imagen, o null si no hay
     * margen que quitar. El fondo se toma de la esquina superior izquierda.
     *
     * @return array{x:int, y:int, width:int, height:int}|null
     */
    private static function cajaDelContenido(GdImage $imagen): ?array
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $fondo = imagecolorsforindex($imagen, imagecolorat($imagen, 0, 0));

        $esFondo = function (int $x, int $y) use ($imagen, $fondo): bool {
            $p = imagecolorsforindex($imagen, imagecolorat($imagen, $x, $y));

            // Casi transparente (alpha de GD: 0 opaco, 127 transparente).
            if ($p['alpha'] >= 120 && $fondo['alpha'] >= 120) {
                return true;
            }

            return abs($p['alpha'] - $fondo['alpha']) * 2 <= self::TOLERANCIA
                && abs($p['red'] - $fondo['red']) <= self::TOLERANCIA
                && abs($p['green'] - $fondo['green']) <= self::TOLERANCIA
                && abs($p['blue'] - $fondo['blue']) <= self::TOLERANCIA;
        };

        $filaVacia = function (int $y) use ($ancho, $esFondo): bool {
            for ($x = 0; $x < $ancho; $x++) {
                if (! $esFondo($x, $y)) {
                    return false;
                }
            }

            return true;
        };

        $columnaVacia = function (int $x, int $arriba, int $abajo) use ($esFondo): bool {
            for ($y = $arriba; $y <= $abajo; $y++) {
                if (! $esFondo($x, $y)) {
                    return false;
                }
            }

            return true;
        };

        for ($arriba = 0; $arriba < $alto && $filaVacia($arriba); $arriba++);

        if ($arriba === $alto) {
            return null;   // imagen de un solo color: nada que recortar
        }

        for ($abajo = $alto - 1; $abajo > $arriba && $filaVacia($abajo); $abajo--);
        for ($izquierda = 0; $izquierda < $ancho && $columnaVacia($izquierda, $arriba, $abajo); $izquierda++);
        for ($derecha = $ancho - 1; $derecha > $izquierda && $columnaVacia($derecha, $arriba, $abajo); $derecha--);

        $caja = [
            'x' => $izquierda,
            'y' => $arriba,
            'width' => $derecha - $izquierda + 1,
            'height' => $abajo - $arriba + 1,
        ];

        return $caja['width'] === $ancho && $caja['height'] === $alto ? null : $caja;
    }
}
