<?php

namespace Tests\Feature;

use App\Http\Middleware\ParticipanteAutenticado;
use App\Models\Mision;
use App\Models\Participante;
use App\Models\User;
use Database\Seeders\ContenidoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdenDeMisionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_mover_una_mision_hacia_adelante_reubica_las_intermedias(): void
    {
        $this->seed(ContenidoSeeder::class);
        $mision = Mision::where('slug', 'phishing')->firstOrFail();

        $this->actualizarOrden($mision, 2);

        $this->assertSame(
            ['contrasenas', 'clasificacion', 'phishing', 'politicas', 'ia-fuera-de-control'],
            Mision::visibles()->pluck('slug')->all(),
        );
        $this->assertSame([0, 1, 2, 3, 4], Mision::visibles()->pluck('orden')->map(fn ($orden) => (int) $orden)->all());
        $this->assertTableroOrden(['contrasenas', 'clasificacion', 'phishing', 'politicas', 'ia-fuera-de-control']);
    }

    public function test_mover_una_mision_hacia_atras_reubica_las_intermedias(): void
    {
        $this->seed(ContenidoSeeder::class);
        $mision = Mision::where('slug', 'ia-fuera-de-control')->firstOrFail();

        $this->actualizarOrden($mision, 1);

        $this->assertSame(
            ['phishing', 'ia-fuera-de-control', 'contrasenas', 'clasificacion', 'politicas'],
            Mision::visibles()->pluck('slug')->all(),
        );
        $this->assertSame([0, 1, 2, 3, 4], Mision::visibles()->pluck('orden')->map(fn ($orden) => (int) $orden)->all());
        $this->assertTableroOrden(['phishing', 'ia-fuera-de-control', 'contrasenas', 'clasificacion', 'politicas']);
    }

    private function actualizarOrden(Mision $mision, int $orden): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('dashboard.misiones.actualizar', $mision), [
                'titulo' => $mision->titulo,
                'etiqueta' => $mision->etiqueta,
                'icono' => $mision->icono,
                'brief' => $mision->brief,
                'instruccion' => $mision->instruccion,
                'duracion' => $mision->duracion,
                'intentos' => $mision->intentos,
                'orden' => $orden,
                'jugable' => $mision->jugable ? 1 : 0,
                'visible' => $mision->visible ? 1 : 0,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    private function assertTableroOrden(array $slugs): void
    {
        $participante = Participante::create([
            'nombre' => 'Ana',
            'documento' => '123',
            'codigo_acceso' => 'ABC234',
        ]);

        $this->withSession([ParticipanteAutenticado::CLAVE_SESION => $participante->id])
            ->get(route('hub'))
            ->assertOk()
            ->assertSeeInOrder(array_map(fn (string $slug): string => "/mision/{$slug}", $slugs), false);
    }
}
