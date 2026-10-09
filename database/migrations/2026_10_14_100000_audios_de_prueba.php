<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Audios de prueba de las pantallas (resources/audio): uno por estación,
     * la consola y el centro de mando. storage/app/public no viaja en git, así
     * que se copian aquí. Solo se asignan donde aún no hay un audio que exista:
     * nunca reemplazan uno cargado desde el dashboard.
     */
    private const MISIONES = ['phishing', 'contrasenas', 'clasificacion', 'politicas', 'ia-fuera-de-control'];

    private const PANTALLAS = ['consola', 'hub'];

    public function up(): void
    {
        foreach (self::MISIONES as $slug) {
            $mision = DB::table('misiones')->where('slug', $slug)->first();

            if ($mision && ! $this->existe($mision->audio_ruta)) {
                DB::table('misiones')->where('id', $mision->id)->update(['audio_ruta' => $this->copiar($slug)]);
            }
        }

        foreach (self::PANTALLAS as $pantalla) {
            $clave = "audio_{$pantalla}";

            if (! $this->existe(DB::table('ajustes')->where('clave', $clave)->value('valor'))) {
                DB::table('ajustes')->updateOrInsert(
                    ['clave' => $clave],
                    ['valor' => $this->copiar($pantalla), 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        foreach (self::MISIONES as $slug) {
            DB::table('misiones')->where('slug', $slug)->where('audio_ruta', $this->ruta($slug))->update(['audio_ruta' => null]);
        }

        foreach (self::PANTALLAS as $pantalla) {
            DB::table('ajustes')->where('clave', "audio_{$pantalla}")->where('valor', $this->ruta($pantalla))->delete();
        }

        Storage::disk('public')->delete(array_map($this->ruta(...), [...self::MISIONES, ...self::PANTALLAS]));
    }

    private function ruta(string $nombre): string
    {
        return "audios/{$nombre}-prueba.mp3";
    }

    private function copiar(string $nombre): string
    {
        Storage::disk('public')->put($this->ruta($nombre), file_get_contents(resource_path("audio/{$nombre}.mp3")));

        return $this->ruta($nombre);
    }

    private function existe(?string $ruta): bool
    {
        return $ruta !== null && Storage::disk('public')->exists($ruta);
    }
};
