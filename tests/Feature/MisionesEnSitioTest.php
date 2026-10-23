<?php

namespace Tests\Feature;

use App\Http\Middleware\ParticipanteAutenticado;
use App\Models\Mision;
use App\Models\Participante;
use App\Models\PartidaMision;
use App\Models\User;
use App\Services\Juego;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cada misión se resuelve en una estación de la sala. En el celular solo se
 * escanea el QR de la estación —eso arranca el cronómetro— y se escribe el
 * código que entrega la estación.
 */
class MisionesEnSitioTest extends TestCase
{
    use RefreshDatabase;

    private Mision $mision;

    private Participante $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mision = Mision::create([
            'slug' => 'phishing',
            'titulo' => 'Phishing',
            'etiqueta' => 'Misión 1',
            'instruccion' => 'Encuentra el candado.',
            'codigo_presencial' => '4F7-K2',
            'duracion' => 180,
            'intentos' => 2,
        ]);

        $this->ana = Participante::create([
            'nombre' => 'Ana', 'documento' => '1020304050', 'correo' => 'ana@empresa.com', 'codigo_acceso' => 'ABC234',
        ]);
    }

    private function comoAna(): static
    {
        return $this->withSession([ParticipanteAutenticado::CLAVE_SESION => $this->ana->id]);
    }

    private function avance(): PartidaMision
    {
        $juego = app(Juego::class);

        return $juego->avanceDe($juego->partidaDe($this->ana), $this->mision)->refresh();
    }

    private function urlQr(): string
    {
        return route('mision.activar', ['phishing', $this->mision->token_qr]);
    }

    public function test_cada_mision_tiene_su_propio_token_para_el_qr(): void
    {
        $this->assertSame(32, strlen($this->mision->token_qr));
        $this->assertStringEndsWith('/mision/phishing/activar/'.$this->mision->token_qr, $this->mision->urlActivacion());
    }

    public function test_al_entrar_a_la_mision_solo_se_pide_escanear_el_qr_y_el_cronometro_no_corre(): void
    {
        $this->comoAna()->get('/mision/phishing')
            ->assertOk()
            ->assertSee('Escanear código QR')
            ->assertSee('data-escaner', false)
            ->assertSee('Encuentra el candado.');

        // Ir directo a /jugar no arranca el cronómetro: devuelve al escáner.
        $this->comoAna()->get('/mision/phishing/jugar')->assertRedirect(route('mision', 'phishing'));

        $this->assertNull($this->avance()->iniciada_en);
        $this->assertSame('pendiente', $this->avance()->estado);
    }

    public function test_escanear_el_qr_activa_la_mision_y_arranca_el_cronometro(): void
    {
        $this->freezeSecond();

        $this->comoAna()->get($this->urlQr())->assertRedirect(route('mision.jugar', 'phishing'));

        $avance = $this->avance();
        $this->assertSame('en_curso', $avance->estado);
        $this->assertNotNull($avance->iniciada_en);

        $this->travel(30)->seconds();

        $this->comoAna()->get('/mision/phishing/jugar')
            ->assertOk()
            ->assertSee('Código de la misión')
            ->assertSee('data-seconds="150"', false)
            ->assertDontSee('4F7');

        // Volver a la misión con el cronómetro corriendo lleva a la ventana del código.
        $this->comoAna()->get('/mision/phishing')->assertRedirect(route('mision.jugar', 'phishing'));

        // Escanear otra vez no reinicia el cronómetro.
        $this->comoAna()->get($this->urlQr());
        $this->assertEquals($avance->iniciada_en, $this->avance()->iniciada_en);
    }

    public function test_un_qr_con_token_incorrecto_no_activa_la_mision(): void
    {
        $this->comoAna()->get(route('mision.activar', ['phishing', 'token-falso']))
            ->assertRedirect(route('mision', 'phishing'))
            ->assertSessionHasErrors('qr');

        $this->assertNull($this->avance()->iniciada_en);
    }

    public function test_sin_escanear_el_qr_no_se_acepta_ningun_codigo(): void
    {
        $this->comoAna()->post('/mision/phishing/responder', ['codigo' => '4F7K2'])
            ->assertRedirect(route('mision', 'phishing'));

        $avance = $this->avance();
        $this->assertSame(0, $avance->intentos_usados);
        $this->assertSame('pendiente', $avance->estado);
    }

    public function test_el_codigo_correcto_supera_la_mision_sin_importar_mayusculas_espacios_ni_guiones(): void
    {
        $this->comoAna()->get($this->urlQr());
        $this->travel(42)->seconds();

        $this->comoAna()->post('/mision/phishing/responder', ['codigo' => ' 4f7 k2 '])
            ->assertRedirect(route('mision.resultado', 'phishing'));

        $avance = $this->avance();
        $this->assertTrue($avance->superada());
        $this->assertSame(1, $avance->aciertos);
        $this->assertSame(42, $avance->segundos);
        $this->assertSame(1, $avance->partida->refresh()->aciertos);
    }

    public function test_un_codigo_incorrecto_consume_la_oportunidad_y_la_siguiente_exige_escanear_de_nuevo(): void
    {
        $this->comoAna()->get($this->urlQr());

        // Vacío y con tiempo: se pide el código, no se gasta la oportunidad.
        $this->comoAna()->post('/mision/phishing/responder', ['codigo' => ''])->assertSessionHasErrors('codigo');
        $this->assertSame(0, $this->avance()->intentos_usados);

        $this->travel(20)->seconds();
        $this->comoAna()->post('/mision/phishing/responder', ['codigo' => 'ZZZZ']);

        $avance = $this->avance();
        $this->assertSame(1, $avance->intentos_usados);
        $this->assertNull($avance->iniciada_en);
        $this->assertTrue($avance->abierta());

        $this->comoAna()->get('/mision/phishing/resultado')
            ->assertOk()
            ->assertSee('Código incorrecto')
            ->assertSee(route('mision', 'phishing'));

        $this->comoAna()->get('/mision/phishing')->assertOk()->assertSee('Escanear código QR');

        // Sin oportunidades queda fallada (y, al ser la única misión, se reabre en segunda vuelta).
        $this->comoAna()->get($this->urlQr());
        $this->comoAna()->post('/mision/phishing/responder', ['codigo' => 'NOPE'])
            ->assertSessionHas('juicio.resultado', 'fallada');
    }

    public function test_si_el_cronometro_llega_a_cero_la_oportunidad_se_pierde(): void
    {
        $this->comoAna()->get($this->urlQr());
        $this->travel(200)->seconds();

        // El cronómetro del navegador envía el formulario vacío al llegar a cero.
        $this->comoAna()->post('/mision/phishing/responder', ['codigo' => ''])
            ->assertSessionHas('juicio.resultado', 'tiempo');

        $avance = $this->avance();
        $this->assertSame(1, $avance->intentos_usados);
        $this->assertSame(180, $avance->segundos);
    }

    public function test_quien_escanea_sin_sesion_vuelve_a_la_mision_despues_de_entrar(): void
    {
        $this->get($this->urlQr())->assertRedirect(route('login'));

        $this->post(route('acceso'), ['correo' => 'ana@empresa.com'])
            ->assertRedirect($this->urlQr());

        $this->get($this->urlQr())->assertRedirect(route('mision.jugar', 'phishing'));
        $this->assertNotNull($this->avance()->iniciada_en);
    }

    public function test_con_la_opcion_activa_el_codigo_vale_en_cualquier_orden(): void
    {
        $this->mision->update(['codigo_presencial' => '123']);
        $this->assertFalse($this->mision->codigoPresencialCoincide('321'));

        $this->mision->update(['codigo_cualquier_orden' => true]);

        foreach (['123', '132', '213', '231', '312', '3-2 1'] as $codigo) {
            $this->assertTrue($this->mision->codigoPresencialCoincide($codigo), $codigo);
        }

        // Mismos caracteres, la misma cantidad de veces: ni más, ni menos, ni otros.
        foreach (['12', '1233', '124', '', null] as $codigo) {
            $this->assertFalse($this->mision->codigoPresencialCoincide($codigo), (string) $codigo);
        }

        $this->mision->update(['codigo_presencial' => '112']);
        $this->assertTrue($this->mision->codigoPresencialCoincide('211'));
        $this->assertFalse($this->mision->codigoPresencialCoincide('122'));

        // De punta a punta: escanear y responder con el código desordenado.
        $this->comoAna()->get($this->urlQr());
        $this->comoAna()->post('/mision/phishing/responder', ['codigo' => '121'])
            ->assertRedirect(route('mision.resultado', 'phishing'));
        $this->assertTrue($this->avance()->superada());
    }

    public function test_el_dashboard_guarda_el_codigo_imprime_los_qr_y_puede_regenerarlos(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put(route('dashboard.misiones.actualizar', $this->mision), [
            'titulo' => 'Phishing', 'icono' => 'mail', 'duracion' => 180, 'intentos' => 2, 'orden' => 0,
            'codigo_presencial' => '  NUEVO1  ', 'jugable' => 1, 'visible' => 1, 'codigo_cualquier_orden' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame('NUEVO1', $this->mision->refresh()->codigo_presencial);
        $this->assertTrue($this->mision->codigo_cualquier_orden);

        $this->get(route('dashboard.misiones.qr'))
            ->assertOk()
            ->assertSee($this->mision->urlActivacion());

        $anterior = $this->urlQr();
        $this->post(route('dashboard.misiones.qr.regenerar', $this->mision))->assertSessionHas('exito');
        $this->assertNotSame($anterior, route('mision.activar', ['phishing', $this->mision->refresh()->token_qr]));

        // El QR impreso antes deja de activar la misión.
        $this->comoAna()->get($anterior)->assertSessionHasErrors('qr');
        $this->assertNull($this->avance()->iniciada_en);
    }

    public function test_la_pantalla_de_proyeccion_muestra_el_qr_y_las_instrucciones(): void
    {
        $segunda = Mision::create(['slug' => 'claves', 'titulo' => 'Claves', 'etiqueta' => 'Misión 2', 'orden' => 1]);

        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard.misiones.proyectar', $this->mision))
            ->assertOk()
            ->assertSee('data-qr="'.e($this->mision->urlActivacion()).'"', false)
            ->assertSee('Escanea para activar la misión')
            ->assertSee('Encuentra el candado.')
            ->assertSee('Estación')
            ->assertSee(route('dashboard.misiones.proyectar', $segunda))
            ->assertDontSee('4F7');

        // El televisor consulta el QR vigente para repintarlo si se regenera.
        $this->getJson(route('dashboard.misiones.proyectar', $this->mision))
            ->assertExactJson(['qr' => $this->mision->urlActivacion()]);

        $this->mision->regenerarTokenQr();

        $this->getJson(route('dashboard.misiones.proyectar', $this->mision))
            ->assertExactJson(['qr' => $this->mision->refresh()->urlActivacion()]);
    }

    public function test_la_pantalla_de_proyeccion_es_publica_y_no_muestra_lo_del_administrador(): void
    {
        $this->mision->update(['codigo_presencial' => null]);

        // Sin sesión: se ve el QR, pero no el aviso ni el botón del dashboard.
        $this->get(route('dashboard.misiones.proyectar', $this->mision))
            ->assertOk()
            ->assertSee('data-qr="'.e($this->mision->urlActivacion()).'"', false)
            ->assertDontSee('aún no tiene código')
            ->assertDontSee('title="Volver al dashboard"', false);

        $this->getJson(route('dashboard.misiones.proyectar', $this->mision))
            ->assertExactJson(['qr' => $this->mision->urlActivacion()]);

        // Con sesión del dashboard sí aparecen.
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard.misiones.proyectar', $this->mision))
            ->assertSee('aún no tiene código')
            ->assertSee('title="Volver al dashboard"', false);
    }
}
