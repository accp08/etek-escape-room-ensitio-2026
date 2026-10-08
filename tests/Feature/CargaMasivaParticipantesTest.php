<?php

namespace Tests\Feature;

use App\Models\Participante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CargaMasivaParticipantesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function importar(string $contenido, bool $actualizar = false)
    {
        return $this->post(route('dashboard.participantes.importar'), [
            'archivo' => UploadedFile::fake()->createWithContent('participantes.csv', $contenido),
            'actualizar' => $actualizar ? '1' : null,
        ]);
    }

    public function test_importa_un_csv_de_excel_en_espanol_y_reporta_las_filas_con_error(): void
    {
        // Excel en español: separador «;», Windows-1252 y encabezados con tildes.
        $csv = mb_convert_encoding(
            "Nombre;Cédula;Área;Correo\r\n"
            ."José Núñez;1001;Compras;jose@empresa.com\r\n"
            ."María Gómez;1002;;MARIA@empresa.com \r\n"
            .";1003;TI;sin.nombre@empresa.com\r\n" // sin nombre
            ."Repetido;1001;TI;otro@empresa.com\r\n" // documento repetido en el archivo
            ."Mal correo;1004;TI;no-es-correo\r\n"
            ."Sin correo;1005;TI;\r\n"            // el correo es el usuario: obligatorio
            ."Correo repetido;1006;TI;jose@empresa.com\r\n"
            .";;;\r\n",                            // fila vacía: se ignora
            'Windows-1252', 'UTF-8');

        $this->importar($csv)->assertRedirect()->assertSessionHas('importacion', function ($r) {
            return $r['creados'] === 2 && $r['omitidos'] === 0
                && array_keys($r['errores']) === [4, 5, 6, 7, 8];
        });

        $jose = Participante::where('documento', '1001')->firstOrFail();
        $this->assertSame('José Núñez', $jose->nombre);
        $this->assertSame('Compras', $jose->area);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $jose->codigo_acceso);
        $this->assertTrue($jose->activo);
        $this->assertNull(Participante::where('documento', '1002')->value('area'));
        $this->assertSame('maria@empresa.com', Participante::where('documento', '1002')->value('correo'));
    }

    public function test_sin_encabezados_usa_el_orden_de_la_plantilla_y_respeta_los_existentes(): void
    {
        $existente = Participante::create(['nombre' => 'Ana', 'documento' => 'A1', 'correo' => 'ana@empresa.com', 'codigo_acceso' => 'ABC234']);

        $this->importar("Ana Actualizada,a1,Legal,ana.nueva@empresa.com\nLuis,B2,TI,luis@empresa.com\n")
            ->assertSessionHas('importacion', fn ($r) => $r['creados'] === 1 && $r['omitidos'] === 1);

        $this->assertSame('Ana', $existente->refresh()->nombre);

        $this->importar("Ana Actualizada,a1,Legal,ana.nueva@empresa.com\n", actualizar: true)
            ->assertSessionHas('importacion', fn ($r) => $r['actualizados'] === 1);

        $existente->refresh();
        $this->assertSame('Ana Actualizada', $existente->nombre);
        $this->assertSame('Legal', $existente->area);
        $this->assertSame('ana.nueva@empresa.com', $existente->correo);
    }

    public function test_la_plantilla_y_la_exportacion_se_descargan(): void
    {
        Participante::create(['nombre' => 'Ana', 'documento' => 'A1', 'correo' => 'Ana@Empresa.com', 'codigo_acceso' => 'ABC234']);

        $plantilla = $this->get(route('dashboard.participantes.plantilla'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Nombre;Documento;Área;Correo', $plantilla);

        $exportado = $this->get(route('dashboard.participantes.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Ana;A1;;ana@empresa.com;Sí', $exportado);
    }

    public function test_rechaza_archivos_que_no_son_csv(): void
    {
        $this->post(route('dashboard.participantes.importar'), [
            'archivo' => UploadedFile::fake()->create('lista.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ])->assertSessionHasErrors('archivo');

        $this->assertSame(0, Participante::count());
    }
}
