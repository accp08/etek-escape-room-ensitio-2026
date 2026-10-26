<?php

namespace Tests\Feature;

use App\Models\Participante;
use App\Models\Partida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultadosDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_el_correo_en_una_columna_independiente_del_participante(): void
    {
        $participante = Participante::create([
            'nombre' => 'Ana Pérez',
            'documento' => '12345678',
            'correo' => 'ana@example.com',
            'codigo_acceso' => Participante::generarCodigo(),
        ]);

        Partida::create([
            'participante_id' => $participante->id,
            'estado' => 'en_curso',
            'aciertos' => 3,
            'aciertos_posibles' => 5,
            'segundos_jugados' => 120,
            'codigo_final_en' => now(),
        ]);

        $respuesta = $this->actingAs(User::factory()->create())
            ->get(route('dashboard.resultados'))
            ->assertOk();

        $respuesta->assertSeeTextInOrder(['Participante', 'Correo', 'Área']);
        $respuesta->assertSeeText('ana@example.com');
    }

    public function test_exporta_el_correo_en_una_columna_compatible_con_excel_en_espanol(): void
    {
        $participante = Participante::create([
            'nombre' => 'Ana Pérez',
            'documento' => '87654321',
            'correo' => 'ana@example.com',
            'codigo_acceso' => Participante::generarCodigo(),
        ]);

        Partida::create([
            'participante_id' => $participante->id,
            'estado' => 'en_curso',
            'aciertos' => 3,
            'aciertos_posibles' => 5,
            'segundos_jugados' => 120,
            'codigo_final_en' => now(),
        ]);

        $contenido = $this->actingAs(User::factory()->create())
            ->get(route('dashboard.resultados.csv'))
            ->assertOk()
            ->assertStreamed()
            ->streamedContent();

        $this->assertStringContainsString(";Correo;", $contenido);
        $this->assertStringContainsString(";ana@example.com;", $contenido);
    }
}
