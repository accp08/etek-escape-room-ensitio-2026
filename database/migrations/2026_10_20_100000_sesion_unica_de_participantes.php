<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una sola sesión activa por participante: al entrar se guarda aquí un
     * token nuevo y cualquier otra sesión abierta con el mismo participante
     * deja de valer (la nueva saca a la anterior).
     */
    public function up(): void
    {
        Schema::table('participantes', function (Blueprint $t) {
            $t->string('sesion_token', 64)->nullable()->after('ubicacion');
        });
    }

    public function down(): void
    {
        Schema::table('participantes', function (Blueprint $t) {
            $t->dropColumn('sesion_token');
        });
    }
};
