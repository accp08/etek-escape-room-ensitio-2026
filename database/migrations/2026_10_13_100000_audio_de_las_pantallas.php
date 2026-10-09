<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audio (MP3) que suena en la pantalla de proyección de cada estación. El
     * de la consola de recuperación se guarda como ajuste (audio_consola).
     */
    public function up(): void
    {
        Schema::table('misiones', function (Blueprint $t) {
            $t->string('audio_ruta')->nullable()->after('token_qr');
        });
    }

    public function down(): void
    {
        Schema::table('misiones', function (Blueprint $t) {
            $t->dropColumn('audio_ruta');
        });
    }
};
