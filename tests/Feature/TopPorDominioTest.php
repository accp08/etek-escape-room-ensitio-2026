<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\Participante;
use App\Models\Partida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /top es una pantalla pública con un podio (top 3) por cada dominio
 * permitido que tenga jugadores. Sin dominios configurados muestra un único
 * podio general.
 */
class TopPorDominioTest extends TestCase
{
    use RefreshDatabase;

    private function jugador(string $nombre, string $correo, int $aciertos, int $segundos): void
    {
        $participante = Participante::create([
            'nombre' => $nombre,
            'documento' => (string) random_int(1000000, 9999999),
            'correo' => $correo,
            'codigo_acceso' => Participante::generarCodigo(),
        ]);

        Partida::create([
            'participante_id' => $participante->id,
            'estado' => 'completada',
            'aciertos' => $aciertos,
            'aciertos_posibles' => 5,
            'segundos_jugados' => $segundos,
            'codigo_final_en' => now(),   // solo entra al top quien validó su código
        ]);
    }

    public function test_muestra_un_podio_por_dominio_permitido(): void
    {
        Ajuste::create(['clave' => 'dominios_outlook', 'valor' => 'etek.com,filial.com']);

        $this->jugador('Ana Etek', 'ana@etek.com', 5, 300);
        $this->jugador('Beto Etek', 'beto@ETEK.com', 5, 200);
        $this->jugador('Ciro Filial', 'ciro@filial.com', 4, 100);
        $this->jugador('Dora Externa', 'dora@otra.com', 5, 50);

        $respuesta = $this->get(route('top'))->assertOk();

        $respuesta->assertSee('etek.com')->assertSee('filial.com')
            ->assertSee('Ana Etek')->assertSee('Beto Etek')->assertSee('Ciro Filial')
            ->assertDontSee('Dora Externa');

        // Cada podio trae solo su dominio, y en orden: Beto (menos tiempo) antes que Ana.
        $podios = $this->get(route('top.podios'))->assertOk()->assertHeader('X-Firma')->getContent();
        [$etek, $filial] = explode('data-slide', substr($podios, strpos($podios, 'data-slide') + 10), 2);

        $this->assertStringContainsString('Ana Etek', $etek);
        $this->assertStringNotContainsString('Ciro Filial', $etek);
        $this->assertStringContainsString('Ciro Filial', $filial);
        $this->assertStringContainsString('Por definir', $filial);
    }

    public function test_los_dominios_sin_jugadores_no_se_muestran(): void
    {
        Ajuste::create(['clave' => 'dominios_outlook', 'valor' => 'etek.com,vacio.com']);

        $this->jugador('Ana Etek', 'ana@etek.com', 5, 300);

        $podios = $this->get(route('top.podios'))->assertOk()->getContent();

        $this->assertStringContainsString('etek.com', $podios);
        $this->assertStringNotContainsString('vacio.com', $podios);
        $this->assertSame(1, substr_count($podios, 'data-slide'));
    }

    public function test_si_ningun_dominio_tiene_jugadores_queda_un_podio_vacio(): void
    {
        Ajuste::create(['clave' => 'dominios_outlook', 'valor' => 'etek.com,vacio.com']);

        $podios = $this->get(route('top.podios'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($podios, 'data-slide'));
        $this->assertStringContainsString('Por definir', $podios);
        $this->assertStringNotContainsString('vacio.com', $podios);
    }

    public function test_sin_dominios_muestra_el_podio_general(): void
    {
        $this->jugador('Ana', 'ana@etek.com', 5, 300);
        $this->jugador('Dora', 'dora@otra.com', 5, 50);

        $this->get(route('top'))->assertOk()
            ->assertSee('Ranking general')
            ->assertSeeInOrder(['Ana', 'Dora']);   // visualmente: 2.º, 1.º, 3.º
    }

    public function test_la_firma_cambia_solo_si_cambia_el_podio(): void
    {
        $this->jugador('Ana', 'ana@etek.com', 5, 300);

        $firma = $this->get(route('top.podios'))->headers->get('X-Firma');
        $this->assertSame($firma, $this->get(route('top.podios'))->headers->get('X-Firma'));

        $this->jugador('Beto', 'beto@etek.com', 5, 100);
        $this->assertNotSame($firma, $this->get(route('top.podios'))->headers->get('X-Firma'));
    }

    public function test_sin_validar_el_codigo_en_la_consola_no_aparece(): void
    {
        $this->jugador('Ana', 'ana@etek.com', 5, 300);
        Partida::latest('id')->first()->update(['codigo_final_en' => null]);

        $this->get(route('top'))->assertOk()->assertDontSee('Ana');
    }
}
