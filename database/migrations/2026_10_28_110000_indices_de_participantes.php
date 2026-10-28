<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** La lista del dashboard ordena por nombre y filtra por área: con 2.000 filas se nota. */
    public function up(): void
    {
        Schema::table('participantes', function (Blueprint $t) {
            $t->index('nombre');
            $t->index('area');
        });
    }

    public function down(): void
    {
        Schema::table('participantes', function (Blueprint $t) {
            $t->dropIndex(['nombre']);
            $t->dropIndex(['area']);
        });
    }
};
