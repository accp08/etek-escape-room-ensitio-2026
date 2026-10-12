<?php

use App\Support\TextosNuevos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Los textos de la pantalla proyectada de cada estación quedan editables en el dashboard. */
    public function up(): void
    {
        TextosNuevos::instalar();
    }

    public function down(): void
    {
        DB::table('textos')->whereIn('clave', array_column(TextosNuevos::PROYECCION, 1))->delete();
    }
};
