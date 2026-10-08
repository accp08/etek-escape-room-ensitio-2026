<?php

namespace Tests\Feature;

use App\Http\Middleware\ParticipanteAutenticado;
use App\Models\Mision;
use App\Models\Participante;
use App\Services\Juego;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodigosPorParticipanteTest extends TestCase
{
    use RefreshDatabase;

    private Juego $juego;

    protected function setUp(): void
    {
        parent::setUp();

        $this->juego = app(Juego::class);
    }

    private function mision(string $slug, int $orden): Mision
    {
        return Mision::create([
            'slug' => $slug,
            'titulo' => ucfirst($slug),
            'etiqueta' => ucfirst($slug),
            'codigo_presencial' => 'COD'.strtoupper($slug),
            'orden' => $orden,
        ]);
    }

    private function participante(string $documento): Participante
    {
        return Participante::create([
            'nombre' => "Jugador {$documento}",
            'documento' => $documento,
            'codigo_acceso' => Participante::generarCodigo(),
        ]);
    }

    /** Supera todas las misiones del participante y devuelve su partida. */
    private function superarTodo(Participante $participante)
    {
        $partida = $this->juego->crearPartida($participante);

        foreach ($partida->misiones as $avance) {
            $avance = $this->juego->comenzar($avance);
            $this->juego->responder($avance, $avance->mision->codigo_presencial);
        }

        return $partida->refresh();
    }

    public function test_cada_participante_nace_con_un_fragmento_propio_por_mision(): void
    {
        $uno = $this->mision('uno', 1);
        $dos = $this->mision('dos', 2);

        $ana = $this->participante('1');
        $beto = $this->participante('2');

        $this->assertCount(2, $ana->codigos);
        $this->assertSame(3, strlen($ana->codigos->first()->fragmento));
        $this->assertNotSame(
            $ana->asegurarCodigos()->get($uno->id),
            $beto->asegurarCodigos()->get($uno->id),
        );
        $this->assertNotNull($beto->asegurarCodigos()->get($dos->id));
    }

    public function test_el_codigo_de_otro_participante_no_sirve(): void
    {
        $this->mision('uno', 1);
        $this->mision('dos', 2);

        $ana = $this->participante('1');
        $beto = $this->participante('2');

        $codigoAna = $this->juego->codigoObtenido($this->superarTodo($ana));
        $codigoBeto = $this->juego->codigoObtenido($this->superarTodo($beto));

        $this->assertTrue($this->juego->codigoCorrecto($ana, $codigoAna));
        $this->assertTrue($this->juego->codigoCorrecto($beto, $codigoBeto));
        $this->assertFalse($this->juego->codigoCorrecto($beto, $codigoAna));
    }

    public function test_no_acepta_el_codigo_final_mientras_haya_misiones_sin_superar(): void
    {
        $this->mision('uno', 1);
        $this->mision('dos', 2);
        $participante = $this->participante('1');
        $partida = $this->juego->crearPartida($participante);

        $this->withSession([ParticipanteAutenticado::CLAVE_SESION => $participante->id])
            ->post('/codigo-final', ['codigo' => $this->juego->codigoCompleto($participante)])
            ->assertSessionHasErrors('codigo');

        $this->assertSame('en_curso', $partida->refresh()->estado);
    }

    public function test_una_mision_nueva_genera_fragmento_para_participantes_existentes(): void
    {
        $ana = $this->participante('1');
        $nueva = $this->mision('nueva', 1);

        $this->assertNotNull($ana->asegurarCodigos()->get($nueva->id));
    }
}
