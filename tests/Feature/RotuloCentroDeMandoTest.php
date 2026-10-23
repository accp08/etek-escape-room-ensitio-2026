<?php

namespace Tests\Feature;

use App\Models\Texto;
use App\Models\User;
use Database\Seeders\ContenidoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El rótulo rojo del centro de mando («Ataque en curso») se edita en Parámetros. */
class RotuloCentroDeMandoTest extends TestCase
{
    use RefreshDatabase;

    public function test_parametros_muestra_y_guarda_el_rotulo_del_centro_de_mando(): void
    {
        $this->seed(ContenidoSeeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard.ajustes'))->assertOk()
            ->assertSee('name="estado_ataque"', false)
            ->assertSee('Ataque en curso');

        $this->put(route('dashboard.ajustes.guardar'), [
            'duracion_mision' => 180, 'intentos_por_mision' => 2, 'ranking_minutos_minimos' => 0,
            'juego_titulo' => 'Ciberamenaza Inminente', 'metodo_acceso' => 'correo',
            'estado_ataque' => '  Alerta SierraCol  ',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Alerta SierraCol', Texto::where('clave', 'hub.estado_ataque')->value('valor'));
        $this->assertSame(1, Texto::where('clave', 'hub.estado_ataque')->count());
    }
}
