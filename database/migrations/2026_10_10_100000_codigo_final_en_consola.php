<?php

use App\Support\TextosNuevos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El código de recuperación ya no se escribe en el celular sino en la
     * consola pública que se proyecta en la sala. Se guarda cuándo lo digitó:
     * la partida se marca «completada» sola al terminar las misiones, así que
     * ese estado no basta para saberlo.
     */
    private const TEXTOS = [
        'hub.boton_codigo' => ['Ingresar código de recuperación', 'Ver mi código de recuperación'],
        'final.descripcion' => [
            'Ensambla los fragmentos en el orden de las misiones para liberar los sistemas cifrados.',
            'Cada misión superada te entregó un fragmento. Juntos forman tu código para liberar los sistemas cifrados.',
        ],
    ];

    public function up(): void
    {
        Schema::table('partidas', function (Blueprint $t) {
            $t->timestamp('codigo_final_en')->nullable()->after('finalizada_en');
        });

        TextosNuevos::instalar();

        // Solo si el administrador no los había editado.
        foreach (self::TEXTOS as $clave => [$anterior, $nuevo]) {
            DB::table('textos')->where('clave', $clave)->where('valor', $anterior)->update(['valor' => $nuevo]);
        }
    }

    public function down(): void
    {
        Schema::table('partidas', function (Blueprint $t) {
            $t->dropColumn('codigo_final_en');
        });

        DB::table('textos')->whereIn('clave', array_column(TextosNuevos::CONSOLA, 1))->delete();

        foreach (self::TEXTOS as $clave => [$anterior, $nuevo]) {
            DB::table('textos')->where('clave', $clave)->where('valor', $nuevo)->update(['valor' => $anterior]);
        }
    }
};
