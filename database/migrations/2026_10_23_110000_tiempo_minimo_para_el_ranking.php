<?php

use App\Support\TextosNuevos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Las partidas terminadas en menos de estos minutos no aparecen en el
     * ranking. Se edita en Parámetros; arranca en 0 (sin mínimo) para no
     * cambiar el ranking actual hasta que se configure.
     */
    public function up(): void
    {
        if (! DB::table('ajustes')->where('clave', 'ranking_minutos_minimos')->exists()) {
            DB::table('ajustes')->insert([
                'clave' => 'ranking_minutos_minimos', 'valor' => '0',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        TextosNuevos::instalar();
    }

    public function down(): void
    {
        DB::table('ajustes')->where('clave', 'ranking_minutos_minimos')->delete();
        DB::table('textos')->whereIn('clave', array_column(TextosNuevos::RANKING, 1))->delete();
    }
};
