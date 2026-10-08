<?php

namespace Tests\Feature;

use App\Http\Middleware\ParticipanteAutenticado;
use App\Models\Mision;
use App\Models\Participante;
use App\Models\User;
use App\Services\Estadisticas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El dashboard muestra quién está jugando ahora y en qué escenario: cada
 * petición del celular (o su latido) lo marca como visto.
 */
class JugadoresEnLineaTest extends TestCase
{
    use RefreshDatabase;

    private Mision $mision;

    private Participante $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mision = Mision::create([
            'slug' => 'phishing',
            'titulo' => 'Bandeja Comprometida',
            'etiqueta' => 'Misión 1',
            'instruccion' => 'Encuentra el candado.',
            'codigo_presencial' => '4F7-K2',
            'duracion' => 180,
            'intentos' => 2,
        ]);

        $this->ana = Participante::create([
            'nombre' => 'Ana', 'documento' => '1020304050', 'correo' => 'ana@empresa.com',
            'codigo_acceso' => 'ABC234', 'area' => 'Gestión Humana',
        ]);
    }

    private function comoAna(): static
    {
        return $this->withSession([ParticipanteAutenticado::CLAVE_SESION => $this->ana->id]);
    }

    public function test_entrar_al_centro_de_mando_lo_marca_en_linea(): void
    {
        $this->comoAna()->get(route('hub'))->assertOk();

        $enLinea = app(Estadisticas::class)->jugadoresEnLinea();

        $this->assertSame(1, $enLinea['total']);
        $this->assertSame(0, $enLinea['resolviendo']);
        $this->assertSame('Centro de mando', $enLinea['jugadores']->first()['lugar']);
        $this->assertSame(1, $enLinea['escenarios']->firstWhere('nombre', 'Centro de mando')['cantidad']);
        $this->assertSame(0, $enLinea['escenarios']->firstWhere('nombre', 'Bandeja Comprometida')['cantidad']);
    }

    public function test_con_el_cronometro_corriendo_cuenta_en_la_mision_aunque_vuelva_al_hub(): void
    {
        $this->comoAna()->get(route('mision.activar', ['phishing', $this->mision->token_qr]));
        $this->comoAna()->get(route('hub'))->assertOk();

        $enLinea = app(Estadisticas::class)->jugadoresEnLinea();

        $this->assertSame(1, $enLinea['resolviendo']);
        $this->assertSame('Bandeja Comprometida', $enLinea['jugadores']->first()['lugar']);
    }

    public function test_el_latido_mantiene_la_ubicacion(): void
    {
        $this->comoAna()->get(route('instrucciones'))->assertOk();
        $this->travel(1)->minutes();
        $this->comoAna()->get(route('presencia'))->assertNoContent();

        $this->ana->refresh();
        $this->assertSame('instrucciones', $this->ana->ubicacion);
        $this->assertTrue($this->ana->visto_en->gt(now()->subSeconds(5)));
    }

    public function test_sin_actividad_reciente_deja_de_estar_en_linea(): void
    {
        $this->comoAna()->get(route('hub'))->assertOk();
        $this->travel(Estadisticas::MINUTOS_EN_LINEA + 1)->minutes();

        $this->assertSame(0, app(Estadisticas::class)->jugadoresEnLinea()['total']);
    }

    public function test_el_dashboard_muestra_la_seccion_y_el_fragmento(): void
    {
        $this->comoAna()->get(route('hub'));
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Jugadores en línea')
            ->assertSee('Gestión Humana');

        $this->actingAs($admin)->get(route('dashboard.en-linea'))
            ->assertOk()
            ->assertSee('Ana')
            ->assertSee('Centro de mando');
    }
}
