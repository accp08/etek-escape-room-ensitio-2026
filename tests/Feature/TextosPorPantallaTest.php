<?php

namespace Tests\Feature;

use App\Models\Texto;
use App\Models\User;
use Database\Seeders\ContenidoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TextosPorPantallaTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_textos_se_agrupan_por_pestana_y_pantalla_sin_perder_ninguno(): void
    {
        $this->seed(ContenidoSeeder::class);
        Texto::create(['clave' => 'nuevo.texto', 'grupo' => 'nuevo', 'valor' => 'Hola']);

        $respuesta = $this->actingAs(User::factory()->create())->get('/dashboard/textos')->assertOk();

        $pestanas = $respuesta->viewData('pestanas');
        $this->assertSame(['acceso', 'mando', 'misiones', 'cierre', 'otros'], $pestanas->keys()->all());

        $pantallas = $pestanas->flatMap(fn ($p) => $p['pantallas']);
        $ids = $pantallas->flatMap(fn ($p) => $p['textos'])->pluck('id');

        // Cada texto aparece exactamente una vez.
        $this->assertEqualsCanonicalizing(Texto::pluck('id')->all(), $ids->all());

        $codigo = $pantallas->firstWhere('titulo', 'Ventana del código');
        $this->assertContains('mision.validar', $codigo['textos']->pluck('clave')->all());

        $escanear = $pantallas->firstWhere('titulo', 'Escanear el QR');
        $this->assertContains('mision.escanear_boton', $escanear['textos']->pluck('clave')->all());
        $this->assertNotContains('mision.validar', $escanear['textos']->pluck('clave')->all());
    }

    public function test_al_guardar_vuelve_a_la_pestana_activa(): void
    {
        $this->seed(ContenidoSeeder::class);
        $texto = Texto::where('clave', 'hub.titulo')->first();

        $this->actingAs(User::factory()->create())
            ->put('/dashboard/textos', ['textos' => [$texto->id => 'Sala de control'], 'pestana' => 'mando'])
            ->assertRedirect(route('dashboard.textos').'#mando');

        $this->assertSame('Sala de control', $texto->refresh()->valor);
    }
}
