<?php

namespace Tests\Feature;

use App\Http\Middleware\ParticipanteAutenticado;
use App\Models\Mision;
use App\Models\Participante;
use App\Models\PartidaMision;
use App\Models\User;
use App\Services\Juego;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La misión de clasificación de la información pide cuatro códigos, uno por
 * nivel: pública, interna, reservada y confidencial. Se supera solo con los cuatro.
 */
class CodigosPorNivelDeClasificacionTest extends TestCase
{
    use RefreshDatabase;

    private Mision $mision;

    private Participante $ana;

    private const CODIGOS = [
        'publica' => 'PUB1', 'interna' => 'INT2', 'reservada' => 'RES3', 'confidencial' => 'CON4',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mision = Mision::create([
            'slug' => Mision::SLUG_CLASIFICACION,
            'titulo' => 'Archivo Expuesto',
            'etiqueta' => 'Misión 3',
            'codigos_clasificacion' => self::CODIGOS,
            'duracion' => 180,
            'intentos' => 2,
        ]);

        $this->ana = Participante::create([
            'nombre' => 'Ana', 'documento' => '1020304050', 'correo' => 'ana@empresa.com', 'codigo_acceso' => 'ABC234',
        ]);
    }

    private function comoAna(): static
    {
        return $this->withSession([ParticipanteAutenticado::CLAVE_SESION => $this->ana->id]);
    }

    private function avance(): PartidaMision
    {
        $juego = app(Juego::class);

        return $juego->avanceDe($juego->partidaDe($this->ana), $this->mision)->refresh();
    }

    private function escanear(): void
    {
        $this->comoAna()->get(route('mision.activar', [Mision::SLUG_CLASIFICACION, $this->mision->token_qr]));
    }

    public function test_el_celular_pide_los_cuatro_codigos(): void
    {
        $this->escanear();

        $this->comoAna()->get('/mision/clasificacion/jugar')
            ->assertOk()
            ->assertSeeInOrder(['Pública', 'Interna', 'Reservada', 'Confidencial'])
            ->assertSee('name="codigo[confidencial]"', false)
            ->assertDontSee('CON4');
    }

    public function test_con_los_cuatro_codigos_correctos_se_supera(): void
    {
        $this->escanear();

        $this->comoAna()->post('/mision/clasificacion/responder', ['codigo' => [
            'publica' => ' pub-1 ', 'interna' => 'int2', 'reservada' => 'RES 3', 'confidencial' => 'con4',
        ]])->assertSessionHas('juicio.resultado', 'superada');

        $this->assertTrue($this->avance()->superada());
    }

    public function test_un_nivel_incorrecto_consume_la_oportunidad(): void
    {
        $this->escanear();

        $this->comoAna()->post('/mision/clasificacion/responder', ['codigo' => [
            'publica' => 'PUB1', 'interna' => 'INT2', 'reservada' => 'CON4', 'confidencial' => 'RES3',
        ]])->assertSessionHas('juicio.resultado', 'reintento');

        $this->assertSame(1, $this->avance()->intentos_usados);
    }

    public function test_incompleto_y_con_tiempo_no_gasta_la_oportunidad(): void
    {
        $this->escanear();

        $this->comoAna()->post('/mision/clasificacion/responder', ['codigo' => ['publica' => 'PUB1']])
            ->assertSessionHasErrors('codigo');

        $this->assertSame(0, $this->avance()->intentos_usados);
    }

    public function test_el_dashboard_guarda_los_cuatro_codigos(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard.misiones.editar', $this->mision))
            ->assertOk()
            ->assertSee('name="codigos_clasificacion[reservada]"', false);

        $this->put(route('dashboard.misiones.actualizar', $this->mision), [
            'titulo' => 'Archivo Expuesto', 'icono' => 'folder', 'duracion' => 180, 'intentos' => 2, 'orden' => 0,
            'codigos_clasificacion' => ['publica' => ' A1 ', 'interna' => 'B2', 'reservada' => 'C3', 'confidencial' => ''],
            'jugable' => 1, 'visible' => 1,
        ])->assertSessionHasNoErrors();

        $mision = $this->mision->refresh();
        $this->assertSame('A1', $mision->codigoDeNivel('publica'));
        $this->assertNull($mision->codigoDeNivel('confidencial'));
        $this->assertFalse($mision->tieneCodigo());
    }
}
