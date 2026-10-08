<?php

use App\Support\TextosNuevos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Texto del botón de instrucciones del centro de mando. */
    public function up(): void
    {
        TextosNuevos::instalar();
    }

    public function down(): void
    {
        DB::table('textos')->whereIn('clave', array_column(TextosNuevos::HUB, 1))->delete();
    }
};
