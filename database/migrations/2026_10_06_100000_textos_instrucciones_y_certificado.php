<?php

use App\Support\TextosNuevos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega a una instalación existente los textos editables nuevos: la hoja
     * de instrucciones, el aviso de pendientes y el cuerpo del certificado.
     * En una instalación nueva la tabla está vacía y los crea el seeder.
     */
    public function up(): void
    {
        TextosNuevos::instalar();
    }

    public function down(): void
    {
        DB::table('textos')->whereIn('clave', array_column(TextosNuevos::todos(), 1))->delete();
    }
};
