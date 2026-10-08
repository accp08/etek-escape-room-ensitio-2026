<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Las misiones 4 (Análisis Forense) y 5 (Ingeniería Social) nunca fueron
     * jugables. Se reemplazan por Políticas de Ciberseguridad y Ransomware,
     * tomando el contenido de config/escape_room.php.
     *
     * Se conserva la fila (id, orden, fragmento, intentos y visibilidad) para
     * no alterar el orden del centro de mando ni el código de recuperación.
     * Solo se toca una misión que siga bloqueada.
     */
    private const REEMPLAZOS = [
        'forense' => 'politicas',
        'ingenieria-social' => 'ransomware',
    ];

    public function up(): void
    {
        $nuevas = collect(config('escape_room.misiones', []))->keyBy('slug');

        foreach (self::REEMPLAZOS as $anterior => $slug) {
            $fila = DB::table('misiones')->where('slug', $anterior)->first();
            $nueva = $nuevas->get($slug);

            if (! $fila || $fila->jugable || ! $nueva || DB::table('misiones')->where('slug', $slug)->exists()) {
                continue;
            }

            $extra = collect($nueva)->only(['politicas'])->filter()->all();

            DB::table('misiones')->where('id', $fila->id)->update([
                'slug' => $slug,
                'motor' => $nueva['motor'] ?? 'codigo',   // el config ya no declara motores
                'titulo' => $nueva['titulo'],
                'etiqueta' => $nueva['etiqueta'],
                'icono' => $nueva['icono'],
                'brief' => $nueva['brief'],
                'instruccion' => $nueva['instruccion'],
                'duracion' => $nueva['duracion'],
                'objetivo_conteo' => null,
                'jugable' => true,
                'extra' => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
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
    }

    /** El contenido anterior ya no existe en el config: no hay a qué volver. */
    public function down(): void {}
};
