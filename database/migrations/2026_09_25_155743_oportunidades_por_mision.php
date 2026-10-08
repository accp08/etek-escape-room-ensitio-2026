<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Oportunidades por misión.
     *
     * Cada oportunidad tiene su propio cronómetro y los tiempos se suman.
     * `misiones.intentos = 0` significa oportunidades ilimitadas, así que el
     * contador de intentos usados deja de caber en un tinyint.
     *
     * Los textos que describían la regla anterior («el cronómetro sigue
     * corriendo») solo se reemplazan si el administrador no los había editado.
     */
    private const TEXTOS = [
        'resultado.reintento_titulo' => [
            'Aún queda tiempo',
            'Tienes otra oportunidad',
        ],
        'resultado.reintento_texto' => [
            'Tu análisis no fue correcto, pero el cronómetro sigue corriendo. Tienes un segundo y último intento.',
            'Tu análisis no fue correcto. En la siguiente oportunidad el cronómetro vuelve a empezar y el tiempo se suma a tu total.',
        ],
        'mision.aviso_tiempo' => [
            'El cronómetro inicia al tocar el botón. Si fallas y aún queda tiempo, tendrás un segundo intento.',
            'El cronómetro inicia al tocar el botón. Cada oportunidad tiene su propio tiempo y todos se suman a tu total.',
        ],
    ];

    public function up(): void
    {
        Schema::table('partida_misiones', function (Blueprint $t) {
            $t->unsignedSmallInteger('intentos_usados')->default(0)->change();
        });

        foreach (self::TEXTOS as $clave => [$anterior, $nuevo]) {
            DB::table('textos')->where('clave', $clave)->where('valor', $anterior)->update(['valor' => $nuevo]);
        }
    }

    public function down(): void
    {
        Schema::table('partida_misiones', function (Blueprint $t) {
            $t->unsignedTinyInteger('intentos_usados')->default(0)->change();
        });

        foreach (self::TEXTOS as $clave => [$anterior, $nuevo]) {
            DB::table('textos')->where('clave', $clave)->where('valor', $nuevo)->update(['valor' => $anterior]);
        }
    }
};
