<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Bitácora de actividad: quién hizo qué, tanto en el dashboard como en el juego. */
    public function up(): void
    {
        Schema::create('registros', function (Blueprint $t) {
            $t->id();
            $t->string('canal')->default('dashboard');   // dashboard | juego | sistema
            $t->string('tipo');                          // acceso, mision.superada, contenido.editado…
            $t->string('nivel')->default('info');        // info | aviso | alerta
            $t->string('descripcion');
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('participante_id')->nullable()->constrained('participantes')->nullOnDelete();
            $t->json('contexto')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();

            $t->index(['canal', 'created_at']);
            $t->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros');
    }
};
