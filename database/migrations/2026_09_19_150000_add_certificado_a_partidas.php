<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seguimiento del certificado.
     *
     * La descarga ocurre en el navegador (window.print → «Guardar como PDF»),
     * así que el servidor solo puede saber que el participante ABRIÓ la
     * pantalla y que PULSÓ el botón de descarga. Si después cancela el diálogo
     * de impresión, eso ya no es observable.
     */
    public function up(): void
    {
        Schema::table('partidas', function (Blueprint $t) {
            $t->timestamp('certificado_visto_en')->nullable()->after('segundos_jugados');
            $t->timestamp('certificado_descargado_en')->nullable()->after('certificado_visto_en');
            $t->unsignedSmallInteger('certificado_descargas')->default(0)->after('certificado_descargado_en');
        });
    }

    public function down(): void
    {
        Schema::table('partidas', function (Blueprint $t) {
            $t->dropColumn(['certificado_visto_en', 'certificado_descargado_en', 'certificado_descargas']);
        });
    }
};
