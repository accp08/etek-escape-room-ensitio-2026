<?php

namespace Tests\Feature;

use App\Http\Middleware\ParticipanteAutenticado;
use App\Models\Participante;
use App\Models\User;
use Database\Seeders\ContenidoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccesoConCorreoTest extends TestCase
{
    use RefreshDatabase;

    private Participante $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ContenidoSeeder::class);
        $this->ana = Participante::create([
            'nombre' => 'Ana', 'correo' => ' Ana.Perez@Empresa.com ', 'codigo_acceso' => 'ABC234',
        ]);
    }

    public function test_entra_solo_con_su_correo(): void
    {
        $this->assertSame('ana.perez@empresa.com', $this->ana->correo);

        // Las mayúsculas y los espacios en el correo no importan.
        $this->post(route('acceso'), ['correo' => ' ANA.PEREZ@empresa.com '])
            ->assertRedirect(route('instrucciones'))
            ->assertSessionHas(ParticipanteAutenticado::CLAVE_SESION, $this->ana->id);
    }

    public function test_rechaza_correos_desconocidos_y_participantes_inactivos(): void
    {
        $this->post(route('acceso'), ['correo' => 'nadie@empresa.com'])
            ->assertSessionHasErrors('correo')
            ->assertSessionMissing(ParticipanteAutenticado::CLAVE_SESION);

        $this->ana->update(['activo' => false]);

        $this->post(route('acceso'), ['correo' => 'ana.perez@empresa.com'])
            ->assertSessionHasErrors('correo')
            ->assertSessionMissing(ParticipanteAutenticado::CLAVE_SESION);
    }

    public function test_el_formulario_solo_pide_el_correo(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('name="correo"', false)
            ->assertDontSee('type="password"', false)
            ->assertDontSee('name="documento"', false)
            ->assertDontSee('name="codigo"', false);
    }

    public function test_el_dashboard_exige_un_correo_unico(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('dashboard.participantes.crear'), ['nombre' => 'Luis'])
            ->assertSessionHasErrors('correo');

        $this->post(route('dashboard.participantes.crear'), ['nombre' => 'Luis', 'correo' => 'ANA.PEREZ@empresa.com'])
            ->assertSessionHasErrors('correo');

        $this->post(route('dashboard.participantes.crear'), ['nombre' => 'Luis', 'correo' => 'luis@empresa.com'])
            ->assertSessionHasNoErrors();
    }
}
