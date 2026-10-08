<?php

use App\Support\TextosNuevos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Todos los textos de la consola de recuperación quedan editables en el dashboard. */
    public function up(): void
    {
        TextosNuevos::instalar();
    }

    public function down(): void
    {
        DB::table('textos')->whereIn('clave', array_column(TextosNuevos::CONSOLA_DETALLE, 1))->delete();
    }
};
