<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------------- Contenido configurable ---------------- */

        // Niveles de clasificación de la información (misión Archivo Expuesto).
        Schema::create('clasificaciones', function (Blueprint $t) {
            $t->id();
            $t->string('clave')->unique();          // publica, interna, ...
            $t->string('nombre');
            $t->string('color')->default('cian');   // verde | cian | violeta | rosa
            $t->string('descripcion')->nullable();
            $t->unsignedSmallInteger('orden')->default(0);
            $t->timestamps();
        });

        Schema::create('misiones', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('motor');                    // bandeja_correo | login_secuencial | archivo_documentos | ...
            $t->string('titulo');
            $t->string('etiqueta');
            $t->string('icono')->default('shield');
            $t->text('brief')->nullable();
            $t->text('instruccion')->nullable();
            $t->string('fragmento_codigo', 8);
            $t->unsignedSmallInteger('duracion')->default(180);        // segundos
            $t->unsignedTinyInteger('intentos')->default(2);
            $t->unsignedSmallInteger('objetivo_conteo')->nullable();   // nº de maliciosos, etc.
            $t->unsignedSmallInteger('orden')->default(0);
            $t->boolean('jugable')->default(true);  // false = se ve bloqueada ("Próximamente")
            $t->boolean('visible')->default(true);
            $t->json('extra')->nullable();          // política de contraseñas, opciones, buzón...
            $t->timestamps();
        });

        // Un ítem por correo / credencial / documento. La forma exacta depende
        // del motor, por eso va en JSON.
        Schema::create('mision_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mision_id')->constrained('misiones')->cascadeOnDelete();
            $t->unsignedSmallInteger('orden')->default(0);
            $t->json('datos');
            $t->timestamps();
        });

        // Pasos del briefing inicial.
        Schema::create('pasos_briefing', function (Blueprint $t) {
            $t->id();
            $t->string('icono')->default('shield');
            $t->string('titulo');
            $t->text('texto');
            $t->unsignedSmallInteger('orden')->default(0);
            $t->timestamps();
        });

        // Textos de la interfaz, editables desde el dashboard.
        Schema::create('textos', function (Blueprint $t) {
            $t->id();
            $t->string('clave')->unique();          // login.titulo, hub.saludo, ...
            $t->string('grupo')->default('general');
            $t->text('valor');
            $t->string('descripcion')->nullable();  // ayuda para el administrador
            $t->boolean('multilinea')->default(false);
            $t->unsignedSmallInteger('orden')->default(0);
            $t->timestamps();
        });

        // Parámetros globales del juego.
        Schema::create('ajustes', function (Blueprint $t) {
            $t->id();
            $t->string('clave')->unique();
            $t->text('valor')->nullable();
            $t->timestamps();
        });

        /* ---------------- Participantes y partidas ---------------- */

        Schema::create('participantes', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->string('documento')->unique();
            $t->string('area')->nullable();
            $t->string('correo')->nullable();
            $t->string('codigo_acceso', 12);        // visible para el administrador
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        Schema::create('partidas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $t->string('estado')->default('en_curso');   // en_curso | completada
            $t->timestamp('iniciada_en')->nullable();
            $t->timestamp('finalizada_en')->nullable();
            $t->unsignedInteger('segundos_jugados')->default(0);
            $t->unsignedSmallInteger('aciertos')->default(0);
            $t->unsignedSmallInteger('aciertos_posibles')->default(0);
            $t->timestamps();

            $t->index(['estado', 'aciertos']);
        });

        // Estado de cada misión dentro de una partida.
        Schema::create('partida_misiones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partida_id')->constrained('partidas')->cascadeOnDelete();
            $t->foreignId('mision_id')->constrained('misiones')->cascadeOnDelete();

            // pendiente | en_curso | superada | fallada
            $t->string('estado')->default('pendiente');

            $t->unsignedTinyInteger('intentos_usados')->default(0);
            $t->boolean('reactivada')->default(false);   // segunda oportunidad ya concedida
            $t->unsignedSmallInteger('aciertos')->default(0);
            $t->unsignedSmallInteger('aciertos_posibles')->default(0);
            $t->unsignedInteger('segundos')->default(0);
            $t->timestamp('iniciada_en')->nullable();    // arranque del cronómetro (servidor)
            $t->timestamp('finalizada_en')->nullable();
            $t->json('respuestas')->nullable();          // último envío, para revisión
            $t->timestamps();

            $t->unique(['partida_id', 'mision_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partida_misiones');
        Schema::dropIfExists('partidas');
        Schema::dropIfExists('participantes');
        Schema::dropIfExists('ajustes');
        Schema::dropIfExists('textos');
        Schema::dropIfExists('pasos_briefing');
        Schema::dropIfExists('mision_items');
        Schema::dropIfExists('misiones');
        Schema::dropIfExists('clasificaciones');
    }
};
