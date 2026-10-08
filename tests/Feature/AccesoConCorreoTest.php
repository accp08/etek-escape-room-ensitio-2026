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
            'nombre' => 'Ana', 'documento' => '1020304050', 'correo' => ' Ana.Perez@Empresa.com ', 'codigo_acceso' => 'ABC234',
        ]);
    }

    public function test_entra_con_su_correo_y_su_cedula_como_contrasena(): void
    {
        $this->assertSame('ana.perez@empresa.com', $this->ana->correo);

        // Mayúsculas en el correo y puntos en la cédula no importan.
        $this->post(route('acceso'), ['correo' => 'ANA.PEREZ@empresa.com', 'documento' => '1.020.304.050'])
            ->assertRedirect(route('instrucciones'))
            ->assertSessionHas(ParticipanteAutenticado::CLAVE_SESION, $this->ana->id);
    }

    public function test_rechaza_la_cedula_incorrecta_el_codigo_anterior_y_los_inactivos(): void
    {
        foreach (['1020304051', 'ABC234'] as $clave) {
            $this->post(route('acceso'), ['correo' => 'ana.perez@empresa.com', 'documento' => $clave])
                ->assertSessionHasErrors('correo')
                ->assertSessionMissing(ParticipanteAutenticado::CLAVE_SESION);
        }

        $this->ana->update(['activo' => false]);

        $this->post(route('acceso'), ['correo' => 'ana.perez@empresa.com', 'documento' => '1020304050'])
            ->assertSessionHasErrors('correo');
    }

    public function test_el_formulario_pide_correo_y_contrasena(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('name="correo"', false)
            ->assertSee('type="password"', false)
            ->assertDontSee('name="codigo"', false);
    }

    public function test_el_dashboard_exige_un_correo_unico(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('dashboard.participantes.crear'), ['nombre' => 'Luis', 'documento' => '999'])
            ->assertSessionHasErrors('correo');

        $this->post(route('dashboard.participantes.crear'), ['nombre' => 'Luis', 'documento' => '999', 'correo' => 'ANA.PEREZ@empresa.com'])
            ->assertSessionHasErrors('correo');

        $this->post(route('dashboard.participantes.crear'), ['nombre' => 'Luis', 'documento' => '999', 'correo' => 'luis@empresa.com'])
            ->assertSessionHasNoErrors();
    }
}
