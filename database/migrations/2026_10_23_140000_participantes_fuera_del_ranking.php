<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite sacar a un participante del ranking (y del Top) sin impedirle
     * jugar. Arranca en true para no cambiar el ranking actual.
     */
    public function up(): void
    {
        Schema::table('participantes', function (Blueprint $t) {
            $t->boolean('en_ranking')->default(true)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('participantes', function (Blueprint $t) {
            $t->dropColumn('en_ranking');
        });
    }
};
