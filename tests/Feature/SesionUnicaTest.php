<?php

namespace Tests\Feature;

use App\Models\Participante;
use App\Models\Registro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cada participante juega con una sola sesión: si alguien entra con sus
 * datos desde otro dispositivo, la sesión anterior se cierra.
 */
class SesionUnicaTest extends TestCase
{
    use RefreshDatabase;

    private Participante $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ana = Participante::create([
            'nombre' => 'Ana', 'documento' => '1020304050', 'correo' => 'ana@empresa.com', 'codigo_acceso' => 'ABC234',
        ]);
    }

    /** Inicia sesión como Ana y devuelve la sesión resultante (otro «dispositivo»). */
    private function entrarComoAna(): array
    {
        $this->flushSession();
        $this->post(route('acceso'), ['correo' => 'ana@empresa.com', 'documento' => '1020304050'])
            ->assertSessionHasNoErrors();

        return session()->all();
    }

    public function test_entrar_desde_otro_dispositivo_cierra_la_sesion_anterior(): void
    {
        $primero = $this->entrarComoAna();
        $this->withSession($primero)->get(route('hub'))->assertOk();

        $segundo = $this->entrarComoAna();

        // El primer dispositivo queda fuera, con aviso.
        $this->flushSession();
        $this->withSession($primero)->get(route('hub'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('correo');
        $this->assertNull(session('participante_id'));

        // El nuevo sigue jugando.
        $this->flushSession();
        $this->withSession($segundo)->get(route('hub'))->assertOk();

        $this->assertTrue(Registro::where('tipo', 'sesion.reemplazada')->where('participante_id', $this->ana->id)->exists());
    }

    public function test_una_sesion_con_token_falso_no_entra(): void
    {
        $this->entrarComoAna();

        $this->flushSession();
        $this->withSession(['participante_id' => $this->ana->id, 'participante_token' => 'inventado'])
            ->get(route('hub'))
            ->assertRedirect(route('login'));
    }

    public function test_las_paginas_con_sesion_no_se_guardan_en_cache(): void
    {
        $sesion = $this->entrarComoAna();

        $cache = $this->withSession($sesion)->get(route('hub'))->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cache);
        $this->assertStringContainsString('private', $cache);

        $this->assertStringContainsString('no-store', $this->get(route('login'))->headers->get('Cache-Control'));
    }
}
