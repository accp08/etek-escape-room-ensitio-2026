<?php

namespace Tests\Feature;

use App\Models\Mision;
use App\Models\Participante;
use App\Models\Partida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteAreasDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_intentos_tiempo_y_comparacion_de_areas_por_tematica(): void
    {
        $mision = Mision::create([
            'slug' => 'phishing',
            'motor' => 'phishing',
            'titulo' => 'Detectar phishing',
            'etiqueta' => 'Ingeniería social',
            'fragmento_codigo' => 'ABC',
        ]);

        $participanteFuerte = $this->participante('Ana', 'Finanzas');
        $participanteDebil = $this->participante('Luis', 'Operaciones');

        $this->registrarMision($participanteFuerte, $mision, 1, 80, 1);
        $this->registrarMision($participanteDebil, $mision, 3, 150, 0);

        $respuesta = $this->actingAs(User::factory()->create())
            ->get(route('dashboard.reporte-areas', ['tematica' => 'Ingeniería social']))
            ->assertOk()
            ->assertSeeText('Ana')
            ->assertSeeText('Luis')
            ->assertSeeText('Ingeniería social')
            ->assertSeeText('Descargar Excel')
            ->assertSee(route('dashboard.reporte-areas.excel', ['tematica' => 'Ingeniería social']));

        $reporte = $respuesta->viewData('reporte');

        $this->assertSame(1, $reporte->firstWhere('participante', 'Ana')['intentos']);
        $this->assertSame(80, $reporte->firstWhere('participante', 'Ana')['segundos']);
        $this->assertSame('Finanzas', $respuesta->viewData('areaMasFuerte')['area']);
        $this->assertSame('Operaciones', $respuesta->viewData('areaMasDebil')['area']);

        $this->get(route('dashboard.reporte-areas', [
            'tematica' => 'Ingeniería social',
            'area' => 'Finanzas',
        ]))->assertOk()->assertSeeText('Ana')->assertDontSeeText('Luis');
    }

    public function test_descarga_excel_con_los_filtros_y_datos_del_reporte(): void
    {
        $mision = Mision::create([
            'slug' => 'phishing',
            'motor' => 'phishing',
            'titulo' => 'Detectar phishing',
            'etiqueta' => 'Ingeniería social',
            'fragmento_codigo' => 'ABC',
        ]);

        $ana = $this->participante('Ana', 'Finanzas');
        $luis = $this->participante('Luis', 'Operaciones');
        $this->registrarMision($ana, $mision, 2, 95, 1);
        $this->registrarMision($luis, $mision, 4, 140, 0);

        $contenido = $this->actingAs(User::factory()->create())
            ->get(route('dashboard.reporte-areas.excel', [
                'tematica' => 'Ingeniería social',
                'area' => 'Finanzas',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition', 'attachment; filename="reporte-areas-'.now()->format('Y-m-d-Hi').'.xlsx"')
            ->getContent();

        $resumen = $this->leerHojaXlsx($contenido, 'xl/worksheets/sheet1.xml');
        $detalle = $this->leerHojaXlsx($contenido, 'xl/worksheets/sheet2.xml');

        $this->assertStringContainsString('Finanzas', $resumen);
        $this->assertStringContainsString('Precisión', $resumen);
        $this->assertStringContainsString('Participante', $detalle);
        $this->assertStringContainsString('Ana', $detalle);
        $this->assertStringContainsString('ana@empresa.com', $detalle);
        $this->assertStringContainsString('Ingeniería social', $detalle);
        $this->assertStringContainsString('Detectar phishing', $detalle);
        $this->assertStringContainsString('Superada', $detalle);
        $this->assertStringContainsString('01:35', $detalle);
        $this->assertSame(2, substr_count($detalle, '<row r='));
    }

    private function leerHojaXlsx(string $contenido, string $archivoBuscado): string
    {
        $desplazamiento = 0;

        while ($desplazamiento < strlen($contenido)) {
            $cabecera = unpack('Vfirma/vversion/vbanderas/vmetodo/vhora/vfecha/Vcrc/Vcomprimido/Voriginal/vnombre/vextra',
                substr($contenido, $desplazamiento, 30));

            if ($cabecera['firma'] !== 0x04034B50) {
                break;
            }

            $inicioNombre = $desplazamiento + 30;
            $nombre = substr($contenido, $inicioNombre, $cabecera['nombre']);
            $inicioArchivo = $inicioNombre + $cabecera['nombre'] + $cabecera['extra'];
            $comprimido = substr($contenido, $inicioArchivo, $cabecera['comprimido']);

            if ($nombre === $archivoBuscado) {
                return gzinflate($comprimido);
            }

            $desplazamiento = $inicioArchivo + $cabecera['comprimido'];
        }

        $this->fail("No se encontró {$archivoBuscado} dentro del XLSX.");
    }

    private function participante(string $nombre, string $area): Participante
    {
        return Participante::create([
            'nombre' => $nombre,
            'area' => $area,
            'correo' => str($nombre)->lower().'@empresa.com',
            'codigo_acceso' => Participante::generarCodigo(),
        ]);
    }

    private function registrarMision(Participante $participante, Mision $mision, int $intentos, int $segundos, int $aciertos): void
    {
        $partida = Partida::create([
            'participante_id' => $participante->id,
            'estado' => 'completada',
        ]);

        $partida->misiones()->create([
            'mision_id' => $mision->id,
            'estado' => $aciertos ? 'superada' : 'fallada',
            'intentos_usados' => $intentos,
            'segundos' => $segundos,
            'aciertos' => $aciertos,
            'aciertos_posibles' => 1,
            'iniciada_en' => now()->subMinutes(5),
            'finalizada_en' => now(),
        ]);
    }
}
