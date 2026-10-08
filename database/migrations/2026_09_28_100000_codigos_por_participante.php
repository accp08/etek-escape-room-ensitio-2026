<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El fragmento de cada misión deja de ser fijo: cada participante recibe
     * uno propio al crearse, así los códigos no sirven entre jugadores.
     * Además cada oportunidad guarda el orden aleatorio en que se muestran
     * los elementos de la misión.
     */
    public function up(): void
    {
        Schema::create('codigos_mision', function (Blueprint $t) {
            $t->id();
            $t->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $t->foreignId('mision_id')->constrained('misiones')->cascadeOnDelete();
            $t->string('fragmento', 8);
            $t->timestamps();

            $t->unique(['participante_id', 'mision_id']);
            $t->unique(['mision_id', 'fragmento']);   // dos jugadores nunca comparten fragmento
        });

        Schema::table('partida_misiones', function (Blueprint $t) {
            $t->json('orden_items')->nullable()->after('respuestas');
        });

        Schema::table('misiones', function (Blueprint $t) {
            $t->string('fragmento_codigo', 8)->nullable()->change();   // ya no se usa en el juego
        });

        // Participantes que ya existían.
        $misiones = DB::table('misiones')->pluck('id');

        foreach (DB::table('participantes')->pluck('id') as $participante) {
            foreach ($misiones as $mision) {
                DB::table('codigos_mision')->insert([
                    'participante_id' => $participante,
                    'mision_id' => $mision,
                    'fragmento' => $this->fragmentoLibre($mision),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function fragmentoLibre(int $mision): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $largo = (int) config('escape_room.largo_fragmento', 3);

        do {
            $fragmento = '';
            for ($i = 0; $i < $largo; $i++) {
                $fragmento .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
        } while (DB::table('codigos_mision')->where('mision_id', $mision)->where('fragmento', $fragmento)->exists());

        return $fragmento;
    }

    public function down(): void
    {
        Schema::table('partida_misiones', function (Blueprint $t) {
            $t->dropColumn('orden_items');
        });

        Schema::dropIfExists('codigos_mision');
    }
};
