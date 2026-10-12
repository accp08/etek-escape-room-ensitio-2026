<?php

namespace Tests\Feature;

use App\Http\Middleware\ParticipanteAutenticado;
use App\Models\Mision;
use App\Models\Participante;
use App\Models\Partida;
use App\Services\Juego;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El código final se ve en el celular y se digita en la consola pública que
 * se proyecta en la sala, junto al top 10.
 */
class ConsolaRecuperacionTest extends TestCase
{
    use RefreshDatabase;

    private Juego $juego;

    protected function setUp(): void
    {
        parent::setUp();

        $this->juego = app(Juego::class);

        foreach (['uno', 'dos'] as $orden => $slug) {
            Mision::create(['slug' => $slug, 'titulo' => ucfirst($slug), 'etiqueta' => ucfirst($slug),
                'codigo_presencial' => 'COD'.$orden, 'orden' => $orden]);
        }
    }

    private function participante(string $nombre, int $documento): Participante
    {
        return Participante::create(['nombre' => $nombre, 'documento' => (string) $documento,
            'area' => 'Tecnología', 'codigo_acceso' => Participante::generarCodigo()]);
    }

    /** Supera las misiones que se indiquen (todas por defecto), tardando $segundos en cada una. */
    private function jugar(Participante $participante, int $segundos = 30, ?int $superar = null): Partida
    {
        $partida = $this->juego->partidaDe($participante);

        foreach ($partida->misiones()->with('mision')->get()->take($superar ?? PHP_INT_MAX) as $avance) {
            $avance = $this->juego->comenzar($avance);
            $this->travel($segundos)->seconds();
            $this->juego->responder($avance, $avance->mision->codigo_presencial);
        }

        return $partida->refresh();
    }

    private function como(Participante $participante): static
    {
        return $this->withSession([ParticipanteAutenticado::CLAVE_SESION => $participante->id]);
    }

    public function test_el_celular_muestra_el_codigo_completo_y_manda_a_la_consola(): void
    {
        $ana = $this->participante('Ana', 1);
        $this->jugar($ana);

        $this->como($ana)->get('/codigo-final')
            ->assertOk()
            ->assertSee($this->juego->codigoCompleto($ana))
            ->assertSee('consola de recuperación')
            ->assertDontSee('name="codigo"', false);

        $this->como($ana)->getJson('/codigo-final/estado')->assertExactJson(['ingresado' => false]);

        // Antes de digitarlo en la consola no hay victoria.
        $this->como($ana)->get('/amenaza-contenida')->assertRedirect(route('final'));
    }

    public function test_con_misiones_pendientes_el_celular_no_muestra_el_codigo_completo(): void
    {
        $beto = $this->participante('Beto', 2);
        $this->jugar($beto, superar: 1);

        $this->como($beto)->get('/codigo-final')
            ->assertOk()
            ->assertSee('Te faltan')
            ->assertDontSee($this->juego->codigoCompleto($beto));
    }

    public function test_la_consola_es_publica_y_muestra_el_top(): void
    {
        $this->jugar($this->participante('Ana Pérez', 1));

        $this->get('/consola')->assertOk()->assertSee('Ana Pérez')->assertSee('data-casilla', false);
        $this->getJson('/consola/ranking')->assertOk()->assertJsonPath('ranking.0.nombre', 'Ana Pérez');
    }

    public function test_el_codigo_correcto_identifica_al_jugador_y_le_da_su_puesto(): void
    {
        $ana = $this->participante('Ana', 1);
        $partida = $this->jugar($ana);

        // Sin sesión: el código basta. Da igual mayúsculas o separadores.
        $codigo = strtolower(implode('-', str_split($this->juego->codigoCompleto($ana), 2)));

        $this->postJson('/consola/verificar', ['codigo' => $codigo])
            ->assertOk()
            ->assertJson(['resultado' => 'correcto', 'repetido' => false, 'nombre' => 'Ana',
                'puesto' => 1, 'en_top' => true, 'partida' => $partida->id])
            ->assertJsonPath('ranking.0.nombre', 'Ana');

        $this->assertTrue($partida->refresh()->codigoIngresado());
        $this->como($ana)->getJson('/codigo-final/estado')->assertExactJson(['ingresado' => true]);
        $this->como($ana)->get('/codigo-final')->assertRedirect(route('certificado'));
        $this->como($ana)->get('/amenaza-contenida')->assertOk();

        // Volver a digitarlo no lo registra dos veces.
        $this->postJson('/consola/verificar', ['codigo' => $codigo])->assertJson(['repetido' => true, 'puesto' => 1]);
    }

    public function test_un_codigo_incorrecto_se_rechaza(): void
    {
        $ana = $this->participante('Ana', 1);
        $partida = $this->jugar($ana);
        $codigo = $this->juego->codigoCompleto($ana);

        foreach (['ZZZZ', substr($codigo, 0, -1).'Z', ''] as $intento) {
            $this->postJson('/consola/verificar', ['codigo' => $intento])->assertStatus(422);
        }

        $this->assertFalse($partida->refresh()->codigoIngresado());
    }

    public function test_fuera_del_top_10_le_dice_en_que_puesto_va(): void
    {
        // 10 jugadores más rápidos ocupan el top.
        foreach (range(1, 10) as $i) {
            $this->jugar($this->participante("Rápido {$i}", 100 + $i), segundos: 10 + $i);
        }

        $lento = $this->participante('Lento', 999);
        $this->jugar($lento, segundos: 90);

        $this->postJson('/consola/verificar', ['codigo' => $this->juego->codigoCompleto($lento)])
            ->assertOk()
            ->assertJson(['puesto' => 11, 'total' => 11, 'en_top' => false])
            ->assertJsonCount(10, 'ranking');
    }
}
