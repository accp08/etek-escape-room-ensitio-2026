<?php

namespace Tests\Feature;

use App\Models\Mision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cada estación y la consola de recuperación pueden tener un MP3 que suena en
 * bucle en su pantalla proyectada. Se cargan desde el dashboard.
 */
class AudioDeLasPantallasTest extends TestCase
{
    use RefreshDatabase;

    private Mision $mision;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->mision = Mision::create([
            'slug' => 'phishing',
            'titulo' => 'Phishing',
            'etiqueta' => 'Misión 1',
            'codigo_presencial' => 'ABC',
            'duracion' => 180,
            'intentos' => 2,
        ]);
    }

    private function mp3(string $nombre = 'pista.mp3'): UploadedFile
    {
        // Cabecera ID3 mínima: suficiente para que se detecte como audio/mpeg.
        return UploadedFile::fake()->createWithContent($nombre, "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x00", 64));
    }

    private function datosMision(array $extra = []): array
    {
        return [
            'titulo' => 'Phishing',
            'icono' => 'mail',
            'duracion' => 180,
            'intentos' => 2,
            'orden' => 0,
            'jugable' => 1,
            'visible' => 1,
            ...$extra,
        ];
    }

    public function test_la_mision_guarda_reemplaza_y_quita_su_audio(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put(route('dashboard.misiones.actualizar', $this->mision), $this->datosMision(['audio' => $this->mp3()]))
            ->assertSessionHasNoErrors();

        $primero = $this->mision->refresh()->audio_ruta;
        $this->assertNotNull($primero);
        Storage::disk('public')->assertExists($primero);

        $this->get(route('dashboard.misiones.proyectar', $this->mision))
            ->assertSee('data-proy-audio', false)
            ->assertSee($this->mision->audioUrl(), false);

        // Reemplazarlo borra el anterior.
        $this->put(route('dashboard.misiones.actualizar', $this->mision), $this->datosMision(['audio' => $this->mp3('otra.mp3')]));
        $segundo = $this->mision->refresh()->audio_ruta;
        $this->assertNotSame($primero, $segundo);
        Storage::disk('public')->assertMissing($primero);

        // Guardar sin archivo conserva el audio.
        $this->put(route('dashboard.misiones.actualizar', $this->mision), $this->datosMision());
        $this->assertSame($segundo, $this->mision->refresh()->audio_ruta);

        $this->put(route('dashboard.misiones.actualizar', $this->mision), $this->datosMision(['quitar_audio' => 1]));
        $this->assertNull($this->mision->refresh()->audio_ruta);
        Storage::disk('public')->assertMissing($segundo);

        $this->get(route('dashboard.misiones.proyectar', $this->mision))
            ->assertDontSee('data-proy-audio', false);
    }

    public function test_la_mision_rechaza_archivos_que_no_son_mp3(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put(route('dashboard.misiones.actualizar', $this->mision), $this->datosMision([
            'audio' => UploadedFile::fake()->create('pista.wav', 10, 'audio/wav'),
        ]))->assertSessionHasErrors('audio');

        $this->assertNull($this->mision->refresh()->audio_ruta);
    }

    public function test_la_consola_suena_con_el_audio_cargado_en_ajustes(): void
    {
        $this->get(route('consola'))->assertDontSee('data-proy-audio', false);

        $this->actingAs(User::factory()->create());

        $this->post(route('dashboard.ajustes.audio-consola'), ['audio_consola' => $this->mp3()])
            ->assertSessionHasNoErrors();

        $ruta = ajuste('audio_consola');
        Storage::disk('public')->assertExists($ruta);

        $this->get(route('consola'))
            ->assertSee('data-proy-audio', false)
            ->assertSee(route('archivos.publicos', ['ruta' => $ruta]), false);

        $this->delete(route('dashboard.ajustes.audio-consola.quitar'));

        Storage::disk('public')->assertMissing($ruta);
        $this->get(route('consola'))->assertDontSee('data-proy-audio', false);
    }

    public function test_solo_el_dashboard_puede_cargar_el_audio_de_la_consola(): void
    {
        $this->post(route('dashboard.ajustes.audio-consola'), ['audio_consola' => $this->mp3()])
            ->assertRedirect(route('dashboard.login'));

        $this->assertNull(ajuste('audio_consola'));
    }
}
