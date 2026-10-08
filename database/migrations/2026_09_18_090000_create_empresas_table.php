<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Empresa que se muestra en el juego. Hoy se usa una sola fila (la activa),
     * pero la tabla queda lista para alojar varias cuando el juego sea
     * realmente multiempresa.
     */
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre');
            $tabla->string('logo_ruta')->nullable();   // ruta en el disco 'public'
            $tabla->boolean('activa')->default(true);
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
