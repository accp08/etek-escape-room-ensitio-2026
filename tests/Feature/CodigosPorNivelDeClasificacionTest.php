<?php

namespace Tests\Feature;

use App\Http\Middleware\ParticipanteAutenticado;
use App\Models\Mision;
use App\Models\Participante;
use App\Models\PartidaMision;
use App\Models\User;
use App\Services\Juego;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La misión de clasificación de la información (misión 3) pide varios códigos
 * con nombre, que se agregan y quitan en el dashboard. Se supera solo con todos.
 */
class CodigosPorNivelDeClasificacionTest extends TestCase
{
    use RefreshDatabase;

    private Mision $mision;

    private Participante $ana;

    private const CODIGOS = [
        ['nombre' => 'Pública', 'codigo' => 'PUB1'],
        ['nombre' => 'Interna', 'codigo' => 'INT2'],
        ['nombre' => 'Reservada', 'codigo' => 'RES3'],
        ['nombre' => 'Confidencial', 'codigo' => 'CON4'],
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

    public function test_el_celular_pide_los_codigos_configurados(): void
    {
        $this->escanear();

        $this->comoAna()->get('/mision/clasificacion/jugar')
            ->assertOk()
            ->assertSeeInOrder(['Pública', 'Interna', 'Reservada', 'Confidencial'])
            ->assertSee('name="codigo[3]"', false)
            ->assertDontSee('CON4');
    }

    public function test_los_niveles_sin_codigo_no_se_piden(): void
    {
        $this->mision->update(['codigos_clasificacion' => [
            ['nombre' => 'Pública', 'codigo' => 'PUB1'],
            ['nombre' => 'Reservada', 'codigo' => ''],
            ['nombre' => 'Confidencial', 'codigo' => 'CON4'],
        ]]);
        $this->escanear();

        $this->comoAna()->get('/mision/clasificacion/jugar')
            ->assertOk()
            ->assertSeeInOrder(['Pública', 'Confidencial'])
            ->assertDontSee('Reservada')
            ->assertDontSee('name="codigo[2]"', false);

        $this->comoAna()->post('/mision/clasificacion/responder', ['codigo' => ['PUB1', 'CON4']])
            ->assertSessionHas('juicio.resultado', 'superada');
    }

    public function test_con_los_cuatro_codigos_correctos_se_supera(): void
    {
        $this->escanear();

        $this->comoAna()->post('/mision/clasificacion/responder', ['codigo' => [' pub-1 ', 'int2', 'RES 3', 'con4']])->assertSessionHas('juicio.resultado', 'superada');

        $this->assertTrue($this->avance()->superada());
    }

    public function test_un_nivel_incorrecto_consume_la_oportunidad(): void
    {
        $this->escanear();

        $this->comoAna()->post('/mision/clasificacion/responder', ['codigo' => ['PUB1', 'INT2', 'CON4', 'RES3']])->assertSessionHas('juicio.resultado', 'reintento');

        $this->assertSame(1, $this->avance()->intentos_usados);
    }

    public function test_incompleto_y_con_tiempo_no_gasta_la_oportunidad(): void
    {
        $this->escanear();

        $this->comoAna()->post('/mision/clasificacion/responder', ['codigo' => ['PUB1']])
            ->assertSessionHasErrors('codigo');

        $this->assertSame(0, $this->avance()->intentos_usados);
    }

    public function test_el_dashboard_agrega_y_quita_codigos(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard.misiones.editar', $this->mision))
            ->assertOk()
            ->assertSee('name="codigos_clasificacion[3][codigo]"', false)
            ->assertSee('Agregar código');

        $this->put(route('dashboard.misiones.actualizar', $this->mision), [
            'titulo' => 'Archivo Expuesto', 'icono' => 'folder', 'duracion' => 180, 'intentos' => 2, 'orden' => 0,
            'codigos_clasificacion' => [
                ['nombre' => ' Pública ', 'codigo' => ' A1 '],
                ['nombre' => 'Reservada', 'codigo' => ''],
                ['nombre' => 'Secreta', 'codigo' => 'B2'],
            ],
            'jugable' => 1, 'visible' => 1,
        ])->assertSessionHasNoErrors();

        $mision = $this->mision->refresh();
        $this->assertSame([
            ['nombre' => 'Pública', 'codigo' => 'A1'],
            ['nombre' => 'Secreta', 'codigo' => 'B2'],
        ], $mision->codigosDeLaEstacion());
        $this->assertTrue($mision->tieneCodigo());
    }

    public function test_sin_codigos_nadie_puede_superarla(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put(route('dashboard.misiones.actualizar', $this->mision), [
            'titulo' => 'Archivo Expuesto', 'icono' => 'folder', 'duracion' => 180, 'intentos' => 2, 'orden' => 0,
            'jugable' => 1, 'visible' => 1,
        ])->assertSessionHasNoErrors();

        $mision = $this->mision->refresh();
        $this->assertSame([], $mision->codigosDeLaEstacion());
        $this->assertFalse($mision->tieneCodigo());
        $this->assertFalse($mision->codigoPresencialCoincide([]));
    }

    public function test_las_demas_misiones_siguen_con_un_solo_codigo(): void
    {
        $otra = Mision::create(['slug' => 'phishing', 'titulo' => 'Bandeja', 'etiqueta' => 'Misión 1', 'codigo_presencial' => 'X1', 'duracion' => 180, 'intentos' => 2]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard.misiones.editar', $otra))
            ->assertOk()
            ->assertSee('name="codigo_presencial"', false)
            ->assertDontSee('Agregar código');
    }

    public function test_la_migracion_convierte_los_niveles_fijos(): void
    {
        DB::table('misiones')->where('id', $this->mision->id)->update(['codigos_clasificacion' => json_encode([
            'publica' => 'PUB1', 'interna' => 'INT2', 'reservada' => null, 'confidencial' => 'CON4',
        ])]);

        (require database_path('migrations/2026_10_24_110000_codigos_de_la_estacion_variables.php'))->up();

        $this->assertSame([
            ['nombre' => 'Pública', 'codigo' => 'PUB1'],
            ['nombre' => 'Interna', 'codigo' => 'INT2'],
            ['nombre' => 'Confidencial', 'codigo' => 'CON4'],
        ], $this->mision->refresh()->codigosDeLaEstacion());
    }
}
