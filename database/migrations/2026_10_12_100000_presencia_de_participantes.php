<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Presencia del participante para «Jugadores en línea» del dashboard:
     * cuándo se le vio por última vez y en qué pantalla del juego estaba.
     */
    public function up(): void
    {
        Schema::table('participantes', function (Blueprint $t) {
            $t->timestamp('visto_en')->nullable()->index();
            $t->string('ubicacion', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('participantes', function (Blueprint $t) {
            $t->dropIndex(['visto_en']);
            $t->dropColumn(['visto_en', 'ubicacion']);
        });
    }
};
