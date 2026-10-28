<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\Participante;
use App\Models\User;
use App\Support\Contenido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccesoMasivoParticipantesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        foreach (['ana', 'luis', 'eva'] as $i => $nombre) {
            Participante::create(['nombre' => $nombre, 'correo' => "{$nombre}@empresa.com",
                'codigo_acceso' => Participante::generarCodigo(), 'activo' => $i < 2]);
        }
    }

    private function metodo(string $metodo): void
    {
        Ajuste::updateOrCreate(['clave' => 'metodo_acceso'], ['valor' => $metodo]);
        Contenido::olvidar();
    }

    public function test_desactiva_y_activa_a_todos_con_acceso_por_correo(): void
    {
        $this->metodo('correo');

        $this->get(route('dashboard.ajustes'))->assertOk()
            ->assertSee('2 de 3 pueden entrar')->assertSee('Desactivar inicio de sesión de todos');

        $this->post(route('dashboard.ajustes.acceso-participantes'), ['accion' => 'desactivar'])
            ->assertSessionHas('exito', fn ($m) => str_contains($m, '2 participantes ya no pueden entrar'));
        $this->assertSame(0, Participante::where('activo', true)->count());

        $this->post(route('dashboard.ajustes.acceso-participantes'), ['accion' => 'activar'])
            ->assertSessionHas('exito', fn ($m) => str_contains($m, '3 participantes pueden volver a entrar'));
        $this->assertSame(3, Participante::where('activo', true)->count());
    }

    public function test_al_desactivar_quien_esta_jugando_queda_fuera_y_no_puede_volver_a_entrar(): void
    {
        $this->metodo('correo');

        $this->post(route('acceso'), ['correo' => 'ana@empresa.com'])->assertSessionHasNoErrors();
        $this->get(route('hub'))->assertOk();

        $this->post(route('dashboard.ajustes.acceso-participantes'), ['accion' => 'desactivar']);

        $this->get(route('hub'))->assertRedirect();
        $this->post(route('acceso'), ['correo' => 'ana@empresa.com'])->assertSessionHasErrors('correo');
    }

    public function test_con_outlook_no_se_muestra_ni_se_permite(): void
    {
        $this->metodo('outlook');

        $this->get(route('dashboard.ajustes'))->assertOk()->assertDontSee('Desactivar inicio de sesión de todos');

        $this->post(route('dashboard.ajustes.acceso-participantes'), ['accion' => 'desactivar'])
            ->assertSessionHasErrors('accion');
        $this->assertSame(2, Participante::where('activo', true)->count());
    }

    public function test_rechaza_acciones_desconocidas(): void
    {
        $this->metodo('correo');

        $this->post(route('dashboard.ajustes.acceso-participantes'), ['accion' => 'borrar'])->assertSessionHasErrors('accion');
        $this->assertSame(2, Participante::where('activo', true)->count());
    }
}
