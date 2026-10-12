<?php

namespace Tests\Feature;

use App\Http\Middleware\ParticipanteAutenticado;
use App\Models\Ajuste;
use App\Models\Mision;
use App\Models\Participante;
use App\Services\Juego;
use Database\Seeders\ContenidoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Con el código de recuperación ya validado en la consola, el juego terminó:
 * al volver a entrar solo quedan el certificado, el video y el ranking.
 */
class JuegoTerminadoTest extends TestCase
{
    use RefreshDatabase;

    private Participante $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ContenidoSeeder::class);
        Mision::query()->delete();
        Mision::create(['slug' => 'uno', 'titulo' => 'Uno', 'etiqueta' => 'Uno', 'codigo_presencial' => 'COD1', 'orden' => 0]);

        $this->ana = Participante::create([
            'nombre' => 'Ana', 'documento' => '1020304050', 'correo' => 'ana@empresa.com', 'codigo_acceso' => 'ABC234',
        ]);
    }

    private function terminar(): void
    {
        app(Juego::class)->partidaDe($this->ana)->update(['codigo_final_en' => now()]);
    }

    public function test_al_volver_a_entrar_va_directo_al_certificado(): void
    {
        $this->terminar();

        $this->post(route('acceso'), ['correo' => 'ana@empresa.com', 'documento' => '1020304050'])
            ->assertRedirect(route('certificado'));
    }

    public function test_solo_puede_ver_certificado_y_ranking(): void
    {
        $this->terminar();
        $this->withSession([ParticipanteAutenticado::CLAVE_SESION => $this->ana->id]);

        $this->get(route('certificado'))->assertOk();
        $this->get(route('ranking'))->assertOk();
        $this->get(route('victoria'))->assertOk();

        foreach ([route('hub'), route('instrucciones'), route('final'), route('mision', 'uno')] as $url) {
            $this->get($url)->assertRedirect(route('certificado'));
        }
    }

    public function test_sin_terminar_sigue_jugando_normal(): void
    {
        $this->withSession([ParticipanteAutenticado::CLAVE_SESION => $this->ana->id]);

        $this->get(route('hub'))->assertOk();
    }

    public function test_con_todos_los_fragmentos_el_boton_del_codigo_va_sobre_las_misiones(): void
    {
        $this->withSession([ParticipanteAutenticado::CLAVE_SESION => $this->ana->id]);
        $boton = route('final');

        $pagina = $this->get(route('hub'))->assertOk()->getContent();
        $this->assertGreaterThan(strpos($pagina, 'eyebrow anim-in mt-7'), strpos($pagina, $boton));

        $juego = app(Juego::class);
        $avance = $juego->comenzar($juego->partidaDe($this->ana)->misiones()->first());
        $juego->responder($avance, 'COD1');

        $pagina = $this->get(route('hub'))->assertOk()->getContent();
        $this->assertLessThan(strpos($pagina, 'eyebrow anim-in mt-7'), strpos($pagina, $boton));
        $this->assertSame(1, substr_count($pagina, 'href="'.$boton.'"'));
    }

    public function test_la_victoria_tiene_el_boton_del_video_de_cierre(): void
    {
        Ajuste::updateOrCreate(['clave' => 'video_final'], ['valor' => 'video/final.mp4']);
        Cache::flush();
        $this->terminar();
        $this->withSession([ParticipanteAutenticado::CLAVE_SESION => $this->ana->id]);

        // Botón que abre el video en una hoja; el archivo no se pide al cargar la página.
        $this->get(route('victoria'))->assertOk()
            ->assertSee('data-abrir-video', false)
            ->assertSee('data-fuente="'.route('video.final').'"', false)
            ->assertDontSee('src="'.route('video.final').'"', false);
    }
}
