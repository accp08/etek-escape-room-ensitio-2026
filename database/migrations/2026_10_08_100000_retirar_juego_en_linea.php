<?php

use App\Support\TextosNuevos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El juego pasa a ser solo en sitio: cada misión se resuelve en la sala y
     * en el celular solo se escanea el QR y se escribe el código. Se retira
     * todo lo del juego en línea: el contenido de las misiones (correos,
     * credenciales, documentos, escenas, cartas, conversaciones), los niveles
     * de clasificación, el motor de cada misión y la opción de modalidad.
     *
     * Cada misión vale ahora un acierto: superarla.
     */
    private const TEXTOS_RETIRADOS = [
        'mision.iniciar', 'mision.aviso_tiempo', 'mision.confirmar',
        'mision.pendientes_titulo', 'mision.pendientes_aviso',
        'mision.pendientes_seguir', 'mision.pendientes_enviar',
    ];

    public function up(): void
    {
        Schema::dropIfExists('mision_items');
        Schema::dropIfExists('clasificaciones');

        Schema::table('misiones', function (Blueprint $t) {
            $t->dropColumn(['motor', 'objetivo_conteo', 'extra', 'fragmento_codigo']);
        });

        Schema::table('partida_misiones', function (Blueprint $t) {
            $t->dropColumn('orden_items');
        });

        DB::table('partida_misiones')->update(['aciertos_posibles' => 1]);
        DB::table('partida_misiones')->where('estado', 'superada')->update(['aciertos' => 1]);

        foreach (DB::table('partidas')->pluck('id') as $partida) {
            $misiones = DB::table('partida_misiones')->where('partida_id', $partida);

            DB::table('partidas')->where('id', $partida)->update([
                'aciertos' => (clone $misiones)->sum('aciertos'),
                'aciertos_posibles' => (clone $misiones)->count(),
            ]);
        }

        DB::table('ajustes')->where('clave', 'modalidad')->delete();
        DB::table('textos')->whereIn('clave', self::TEXTOS_RETIRADOS)->delete();
        TextosNuevos::instalar();   // botón «Validar código»
    }

    /** Solo la estructura: el contenido en línea no se recupera. */
    public function down(): void
    {
        Schema::table('misiones', function (Blueprint $t) {
            $t->string('motor')->default('codigo')->after('slug');
            $t->string('fragmento_codigo', 8)->nullable();
            $t->unsignedSmallInteger('objetivo_conteo')->nullable();
            $t->json('extra')->nullable();
        });

        Schema::table('partida_misiones', function (Blueprint $t) {
            $t->json('orden_items')->nullable();
        });

        Schema::create('clasificaciones', function (Blueprint $t) {
            $t->id();
            $t->string('clave')->unique();
            $t->string('nombre');
            $t->string('color')->default('cian');
            $t->string('descripcion')->nullable();
            $t->unsignedSmallInteger('orden')->default(0);
            $t->timestamps();
        });

        Schema::create('mision_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mision_id')->constrained('misiones')->cascadeOnDelete();
            $t->unsignedSmallInteger('orden')->default(0);
            $t->json('datos');
            $t->timestamps();
        });
    }
};
