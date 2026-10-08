<?php

use App\Support\TextosNuevos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El participante ahora entra con su correo (usuario) y su cédula
     * (contraseña). El correo se normaliza a minúsculas y pasa a ser único;
     * los textos del formulario anterior se reemplazan por los nuevos.
     */
    public function up(): void
    {
        DB::table('participantes')->update(['correo' => DB::raw("NULLIF(LOWER(TRIM(correo)), '')")]);

        $repetidos = DB::table('participantes')->whereNotNull('correo')
            ->select('correo')->groupBy('correo')->havingRaw('COUNT(*) > 1')->pluck('correo');

        if ($repetidos->isNotEmpty()) {
            throw new RuntimeException(
                'Hay participantes que comparten correo y el correo ahora es el usuario del juego. '
                .'Corrígelos en el dashboard antes de migrar: '.$repetidos->implode(', ')
            );
        }

        Schema::table('participantes', fn (Blueprint $t) => $t->unique('correo'));

        DB::table('textos')->whereIn('clave', TextosNuevos::RETIRADOS)->delete();
        TextosNuevos::instalar();
    }

    public function down(): void
    {
        Schema::table('participantes', fn (Blueprint $t) => $t->dropUnique(['correo']));
        DB::table('textos')->whereIn('clave', array_column(TextosNuevos::LOGIN, 1))->delete();
    }
};
