<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El participante entra solo con su correo: la cédula deja de pedirse en
     * el acceso y en la carga de usuarios. La columna se conserva (opcional)
     * para no perder lo que ya estaba cargado.
     */
    public function up(): void
    {
        Schema::table('participantes', fn (Blueprint $t) => $t->string('documento')->nullable()->change());

        DB::table('textos')->whereIn('clave', ['login.campo_clave', 'login.ayuda_clave'])->delete();
    }

    public function down(): void
    {
        DB::table('participantes')->whereNull('documento')->pluck('id')
            ->each(fn (int $id) => DB::table('participantes')->where('id', $id)->update(['documento' => "SIN-{$id}"]));

        Schema::table('participantes', fn (Blueprint $t) => $t->string('documento')->nullable(false)->change());
    }
};
