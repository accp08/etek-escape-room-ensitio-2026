<?php

namespace Tests\Feature;

use App\Models\Participante;
use App\Models\Partida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListaParticipantesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function participante(string $nombre, array $datos = []): Participante
    {
        return Participante::create($datos + [
            'nombre' => $nombre,
            'correo' => str($nombre)->slug('.').'@empresa.com',
            'codigo_acceso' => Participante::generarCodigo(),
        ]);
    }

    public function test_pagina_de_25_en_25(): void
    {
        foreach (range(1, 30) as $i) {
            $this->participante(sprintf('Persona %02d', $i));
        }

        $pagina = $this->get(route('dashboard.participantes'))->assertOk()
            ->assertSee('Persona 25')->assertDontSee('Persona 26')
            ->assertSee('class="paginador"', false)->assertSee('Siguiente ›')
            ->assertSee('aria-label="Ir a la página 2"', false)
            ->viewData('participantes');
        $this->assertSame([1, 25, 30], [$pagina->firstItem(), $pagina->lastItem(), $pagina->total()]);

        $this->get(route('dashboard.participantes', ['page' => 2]))->assertOk()
            ->assertSee('Persona 26')->assertDontSee('Persona 25');
    }

    public function test_busca_por_nombre_correo_o_area(): void
    {
        $this->participante('Ana Pérez', ['area' => 'Compras']);
        $this->participante('Luis Gómez', ['area' => 'Legal', 'correo' => 'lgomez@otra.com']);

        $this->get(route('dashboard.participantes', ['q' => 'pérez']))->assertSee('Ana Pérez')->assertDontSee('Luis Gómez');
        $this->get(route('dashboard.participantes', ['q' => 'otra.com']))->assertSee('Luis Gómez')->assertDontSee('Ana Pérez');
        $this->get(route('dashboard.participantes', ['area' => 'Compras']))->assertSee('Ana Pérez')->assertDontSee('Luis Gómez');
        $this->get(route('dashboard.participantes', ['q' => 'nadie']))->assertSee('Ningún participante coincide');
    }

    public function test_filtra_por_casillas_y_por_partida(): void
    {
        $this->participante('Inactiva', ['activo' => false]);
        $this->participante('Fuera', ['en_ranking' => false]);
        $jugando = $this->participante('Jugando');
        $termino = $this->participante('Termino');

        Partida::create(['participante_id' => $jugando->id, 'estado' => 'en_curso']);
        Partida::create(['participante_id' => $termino->id, 'estado' => 'completada']);

        $nombres = fn (array $filtro) => $this->get(route('dashboard.participantes', $filtro))
            ->viewData('participantes')->pluck('nombre')->all();

        $this->assertSame(['Inactiva'], $nombres(['estado' => 'inactivos']));
        $this->assertSame(['Fuera'], $nombres(['ranking' => 'fuera']));
        $this->assertSame(['Jugando'], $nombres(['partida' => 'en_curso']));
        $this->assertSame(['Termino'], $nombres(['partida' => 'completada']));
        $this->assertSame(['Fuera', 'Inactiva'], $nombres(['partida' => 'sin_partida']));
        $this->assertCount(4, $nombres(['estado' => 'cualquier-cosa']));   // valor desconocido: se ignora
    }
}
