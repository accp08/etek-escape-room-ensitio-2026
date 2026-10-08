<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La misión «Anatomía del Ransomware» se reemplaza por «IA fuera de
     * control», con el contenido de config/escape_room.php.
     *
     * Se conserva la fila (id, orden, fragmento y visibilidad) para no alterar
     * el centro de mando ni el código de recuperación de cada participante.
     */
    public function up(): void
    {
        $fila = DB::table('misiones')->where('slug', 'ransomware')->first();
        $nueva = collect(config('escape_room.misiones', []))->firstWhere('slug', 'ia-fuera-de-control');

        if (! $fila || ! $nueva || DB::table('misiones')->where('slug', $nueva['slug'])->exists()) {
            return;
        }

        DB::table('misiones')->where('id', $fila->id)->update([
            'slug' => $nueva['slug'],
            'motor' => $nueva['motor'] ?? 'codigo',   // el config ya no declara motores
            'titulo' => $nueva['titulo'],
            'etiqueta' => $nueva['etiqueta'],
            'icono' => $nueva['icono'],
            'brief' => $nueva['brief'],
            'instruccion' => $nueva['instruccion'],
            'duracion' => $nueva['duracion'],
            'intentos' => $nueva['intentos'] ?? 2,
            'objetivo_conteo' => null,
            'jugable' => true,
            'extra' => null,
            'updated_at' => now(),
        ]);

        DB::table('mision_items')->where('mision_id', $fila->id)->delete();

        foreach ($nueva['items'] ?? [] as $orden => $datos) {
            DB::table('mision_items')->insert([
                'mision_id' => $fila->id,
                'orden' => $orden,
                'datos' => json_encode($datos, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** El contenido anterior ya no existe en el config: no hay a qué volver. */
    public function down(): void {}
};
