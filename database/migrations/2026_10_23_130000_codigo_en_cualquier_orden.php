<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Por misión: aceptar el código de la estación con sus caracteres en
     * cualquier orden (si el código es 123, también valen 213, 321…).
     */
    public function up(): void
    {
        Schema::table('misiones', function (Blueprint $t) {
            $t->boolean('codigo_cualquier_orden')->default(false)->after('codigos_clasificacion');
        });
    }

    public function down(): void
    {
        Schema::table('misiones', function (Blueprint $t) {
            $t->dropColumn('codigo_cualquier_orden');
        });
    }
};
