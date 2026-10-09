<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Audios MP3 que suenan en las pantallas proyectadas (estaciones y consola).
 * Se guardan en el disco "public" y se sirven por la ruta archivos.publicos,
 * que responde peticiones por rangos (necesarias para Safari y para adelantar).
 */
class Audio
{
    private const CARPETA = 'audios';

    /** 30 MB: alcanza para varios minutos de música a buena calidad. */
    public const MAX_KB = 30720;

    /** @return array<int, string> */
    public static function reglas(): array
    {
        return [
            'nullable',
            'file',
            'extensions:mp3',
            // Algunos MP3 sin cabecera ID3 se detectan como octet-stream.
            'mimetypes:audio/mpeg,audio/mp3,audio/mpeg3,audio/x-mpeg-3,application/octet-stream',
            'max:'.self::MAX_KB,
        ];
    }

    /** @return array<string, string> */
    public static function mensajes(string $campo): array
    {
        return [
            "{$campo}.extensions" => 'El audio debe ser un archivo MP3.',
            "{$campo}.mimetypes" => 'El audio debe ser un archivo MP3.',
            "{$campo}.max" => 'El audio no puede pesar más de '.intdiv(self::MAX_KB, 1024).' MB.',
            "{$campo}.uploaded" => 'El audio no se pudo subir: puede superar el límite del servidor.',
        ];
    }

    /** Guarda el archivo y borra el que reemplaza. Devuelve la ruta nueva. */
    public static function guardar(UploadedFile $archivo, string $nombre, ?string $anterior = null): string
    {
        $ruta = $archivo->storeAs(self::CARPETA, Str::slug($nombre).'-'.Str::random(8).'.mp3', 'public');

        if ($anterior && $anterior !== $ruta) {
            self::borrar($anterior);
        }

        return $ruta;
    }

    public static function borrar(?string $ruta): void
    {
        if ($ruta) {
            Storage::disk('public')->delete($ruta);
        }
    }

    /** URL pública del audio, o null si no hay. */
    public static function url(?string $ruta): ?string
    {
        if (! $ruta || ! Storage::disk('public')->exists($ruta)) {
            return null;
        }

        return route('archivos.publicos', ['ruta' => $ruta]);
    }
}
