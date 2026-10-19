<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La misión de clasificación de la información pide cuatro códigos, uno
     * por nivel (pública, interna, reservada y confidencial), en lugar del
     * código único de la estación.
     */
    public function up(): void
    {
        Schema::table('misiones', function (Blueprint $t) {
            $t->json('codigos_clasificacion')->nullable()->after('codigo_presencial');
        });
    }

    public function down(): void
    {
        Schema::table('misiones', function (Blueprint $t) {
            $t->dropColumn('codigos_clasificacion');
        });
    }
};
