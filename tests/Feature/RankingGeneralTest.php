<?php

namespace Tests\Feature;

use App\Http\Middleware\ParticipanteAutenticado;
use App\Models\Ajuste;
use App\Models\Mision;
use App\Models\Participante;
use App\Models\Partida;
use App\Models\PartidaMision;
use App\Models\User;
use App\Services\Juego;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /ranking muestra a todos los que ya validaron su código en la consola, de
 * cualquier dominio y sin tope de puestos.
 */
class RankingGeneralTest extends TestCase
{
    use RefreshDatabase;

    private Mision $mision;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mision = Mision::create([
            'slug' => 'uno', 'titulo' => 'Uno', 'etiqueta' => 'Misión 1', 'codigo_presencial' => 'A', 'duracion' => 180, 'intentos' => 2,
        ]);
    }

    private function jugador(string $nombre, string $correo, int $aciertos, int $segundos, bool $jugo = true, bool $validado = false): Participante
    {
        $participante = Participante::create([
            'nombre' => $nombre,
            'documento' => (string) random_int(1000000, 9999999),
            'correo' => $correo,
            'codigo_acceso' => Participante::generarCodigo(),
        ]);

        $partida = Partida::create([
            'participante_id' => $participante->id,
            'estado' => 'en_curso',
            'aciertos' => $aciertos,
            'aciertos_posibles' => 5,
            'segundos_jugados' => $segundos,
            'codigo_final_en' => $validado ? now() : null,
        ]);

        PartidaMision::create([
            'partida_id' => $partida->id,
            'mision_id' => $this->mision->id,
            'estado' => $jugo ? 'fallada' : 'pendiente',
            'intentos_usados' => $jugo ? 1 : 0,
        ]);

        return $participante;
    }

    public function test_muestra_a_todos_los_que_validaron_sin_importar_el_dominio(): void
    {
        $ana = $this->jugador('Ana Etek', 'ana@etek.com', 5, 300, validado: true);
        $this->jugador('Beto Otra', 'beto@otra.com', 5, 200, validado: true);
        $this->jugador('Ciro Gmail', 'ciro@gmail.com', 4, 40, validado: true);
        $this->jugador('Dora Sin Validar', 'dora@etek.com', 5, 10);

        $this->withSession([ParticipanteAutenticado::CLAVE_SESION => $ana->id])
            ->get(route('ranking'))
            ->assertOk()
            ->assertSeeInOrder(['Beto Otra', 'Ana Etek', 'Ciro Gmail'])
            ->assertDontSee('Dora Sin Validar');
    }

    public function test_no_tiene_tope_de_puestos(): void
    {
        $ana = $this->jugador('Ana Etek', 'ana@etek.com', 5, 1, validado: true);

        foreach (range(1, 60) as $i) {
            $this->jugador("Jugador {$i}", "j{$i}@etek.com", 5, 100 + $i, validado: true);
        }

        $this->withSession([ParticipanteAutenticado::CLAVE_SESION => $ana->id])
            ->get(route('ranking'))
            ->assertOk()
            ->assertSee('Jugador 60');
    }

    public function test_oculta_las_partidas_terminadas_en_menos_del_tiempo_minimo(): void
    {
        Ajuste::updateOrCreate(['clave' => 'ranking_minutos_minimos'], ['valor' => '5']);

        $ana = $this->jugador('Ana Lenta', 'ana@etek.com', 5, 300, validado: true);
        $beto = $this->jugador('Beto Rapido', 'beto@etek.com', 5, 299, validado: true);

        $this->withSession([ParticipanteAutenticado::CLAVE_SESION => $ana->id])
            ->get(route('ranking'))
            ->assertOk()
            ->assertSee('Ana Lenta')
            ->assertDontSee('Beto Rapido');

        $juego = app(Juego::class);
        $this->assertSame(1, $juego->puestoDe($ana->partidaActual()));
        $this->assertNull($juego->puestoDe($beto->partidaActual()));
        $this->assertSame(1, $juego->totalRanking());
    }

    public function test_oculta_a_los_participantes_sacados_del_ranking(): void
    {
        $ana = $this->jugador('Ana Visible', 'ana@etek.com', 5, 300, validado: true);
        $beto = $this->jugador('Beto Oculto', 'beto@etek.com', 5, 100, validado: true);

        $this->actingAs(User::factory()->create())
            ->put(route('dashboard.participantes.actualizar', $beto), [
                'nombre' => $beto->nombre, 'correo' => $beto->correo, 'activo' => '1', 'en_ranking' => '0',
            ])
            ->assertRedirect();

        $this->assertFalse($beto->fresh()->en_ranking);
        $this->assertTrue($beto->fresh()->activo);

        $this->withSession([ParticipanteAutenticado::CLAVE_SESION => $ana->id])
            ->get(route('ranking'))
            ->assertOk()
            ->assertSee('Ana Visible')
            ->assertDontSee('Beto Oculto');

        $juego = app(Juego::class);
        $this->assertSame(1, $juego->puestoDe($ana->partidaActual()));
        $this->assertNull($juego->puestoDe($beto->partidaActual()));
        $this->assertSame(1, $juego->totalRanking());
    }
}
