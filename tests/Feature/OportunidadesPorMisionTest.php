<?php

namespace Tests\Feature;

use App\Http\Middleware\ParticipanteAutenticado;
use App\Models\Mision;
use App\Models\Participante;
use App\Models\PartidaMision;
use App\Services\Juego;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OportunidadesPorMisionTest extends TestCase
{
    use RefreshDatabase;

    private Juego $juego;

    protected function setUp(): void
    {
        parent::setUp();

        $this->juego = app(Juego::class);
    }

    /** Crea una misión y devuelve el avance del participante en ella, con el QR ya escaneado. */
    private function avanceCon(int $intentos, int $duracion = 180): PartidaMision
    {
        $mision = Mision::create([
            'slug' => 'prueba',
            'titulo' => 'Prueba',
            'etiqueta' => 'Prueba',
            'codigo_presencial' => 'BIEN',
            'duracion' => $duracion,
            'intentos' => $intentos,
        ]);

        $participante = Participante::create([
            'nombre' => 'Ana',
            'documento' => '123',
            'codigo_acceso' => 'ABC234',
        ]);

        $partida = $this->juego->crearPartida($participante);

        return $this->juego->comenzar($this->juego->avanceDe($partida, $mision));
    }

    private function fallar(PartidaMision $avance): array
    {
        return $this->juego->responder($avance->refresh(), 'MAL');
    }

    public function test_cada_oportunidad_tiene_su_propio_cronometro_y_los_tiempos_se_suman(): void
    {
        $avance = $this->avanceCon(intentos: 3);

        $this->travel(40)->seconds();
        $this->assertSame('reintento', $this->fallar($avance)['resultado']);

        $avance->refresh();
        $this->assertSame(40, $avance->segundos);
        $this->assertNull($avance->iniciada_en);
        $this->assertSame(180, $avance->segundosRestantes());

        $avance = $this->juego->comenzar($avance);
        $this->travel(25)->seconds();
        $resultado = $this->juego->responder($avance->refresh(), 'bien');

        $this->assertSame('superada', $resultado['resultado']);
        $this->assertSame(65, $avance->refresh()->segundos);
        $this->assertSame(65, $avance->partida->refresh()->segundos_jugados);
    }

    public function test_al_agotar_las_oportunidades_la_mision_queda_fallada(): void
    {
        $avance = $this->avanceCon(intentos: 2);

        $this->assertSame('reintento', $this->fallar($avance)['resultado']);

        $this->juego->comenzar($avance->refresh());
        $this->assertSame('fallada', $this->fallar($avance)['resultado']);
    }

    public function test_con_oportunidades_ilimitadas_nunca_queda_fallada(): void
    {
        $avance = $this->avanceCon(intentos: 0);

        foreach (range(1, 8) as $vez) {
            $this->juego->comenzar($avance->refresh());
            $this->travel(10)->seconds();
            $this->assertSame('reintento', $this->fallar($avance)['resultado']);
        }

        $avance->refresh();
        $this->assertTrue($avance->abierta());
        $this->assertSame(8, $avance->intentos_usados);
        $this->assertSame(80, $avance->segundos);
        $this->assertSame('∞', $avance->intentosRestantesLegible());
    }

    public function test_las_pantallas_muestran_oportunidades_ilimitadas_y_tiempo_acumulado(): void
    {
        $avance = $this->avanceCon(intentos: 0);
        $this->travel(75)->seconds();
        $this->fallar($avance);

        $sesion = [ParticipanteAutenticado::CLAVE_SESION => $avance->partida->participante_id];

        $this->withSession($sesion)->get('/mision/prueba')
            ->assertOk()
            ->assertSee('∞')
            ->assertSee('01:15');

        $this->juego->comenzar($avance->refresh());

        $this->withSession($sesion)->get('/mision/prueba/jugar')
            ->assertOk()
            ->assertSee('sin límite');
    }

    public function test_agotar_el_cronometro_consume_la_oportunidad_y_suma_la_duracion_completa(): void
    {
        $avance = $this->avanceCon(intentos: 2, duracion: 60);

        $this->travel(90)->seconds();
        $this->juego->cerrarVencidas($avance->partida);

        $avance->refresh();
        $this->assertTrue($avance->abierta());
        $this->assertSame(1, $avance->intentos_usados);
        $this->assertSame(60, $avance->segundos);

        $this->juego->comenzar($avance);
        $this->travel(61)->seconds();
        $this->assertSame('tiempo', $this->fallar($avance)['resultado']);

        // Al ser la única misión, la segunda vuelta la reabre enseguida,
        // con oportunidades nuevas pero conservando el tiempo acumulado.
        $avance->refresh();
        $this->assertTrue($avance->reactivada);
        $this->assertSame(0, $avance->intentos_usados);
        $this->assertSame(120, $avance->segundos);
    }
}
