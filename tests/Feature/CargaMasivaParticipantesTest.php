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
            "Nombre;Área;Correo\r\n"
            ."José Núñez;Compras;jose@empresa.com\r\n"
            ."María Gómez;;MARIA@empresa.com \r\n"
            .";TI;sin.nombre@empresa.com\r\n"     // sin nombre
            ."Mal correo;TI;no-es-correo\r\n"
            ."Sin correo;TI;\r\n"                // el correo es el usuario: obligatorio
            ."Correo repetido;TI;jose@empresa.com\r\n"
            .";;\r\n",
            'Windows-1252', 'UTF-8');

        $this->importar($csv)->assertRedirect()->assertSessionHas('importacion', function ($r) {
            return $r['creados'] === 2 && $r['omitidos'] === 0
                && array_keys($r['errores']) === [4, 5, 6, 7];
        });

        $jose = Participante::where('correo', 'jose@empresa.com')->firstOrFail();
        $this->assertSame('José Núñez', $jose->nombre);
        $this->assertSame('Compras', $jose->area);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $jose->codigo_acceso);
        $this->assertTrue($jose->activo);
        $this->assertNull($jose->documento);
        $this->assertNull(Participante::where('correo', 'maria@empresa.com')->value('area'));
    }

    public function test_sin_encabezados_usa_el_orden_de_la_plantilla_y_respeta_los_existentes(): void
    {
        $existente = Participante::create(['nombre' => 'Ana', 'correo' => 'ana@empresa.com', 'codigo_acceso' => 'ABC234']);

        $this->importar("Ana Actualizada,Legal,ANA@empresa.com\nLuis,TI,luis@empresa.com\n")
            ->assertSessionHas('importacion', fn ($r) => $r['creados'] === 1 && $r['omitidos'] === 1);

        $this->assertSame('Ana', $existente->refresh()->nombre);

        $this->importar("Ana Actualizada,Legal,ana@empresa.com\n", actualizar: true)
            ->assertSessionHas('importacion', fn ($r) => $r['actualizados'] === 1);

        $existente->refresh();
        $this->assertSame('Ana Actualizada', $existente->nombre);
        $this->assertSame('Legal', $existente->area);
        $this->assertSame('ana@empresa.com', $existente->correo);
    }

    public function test_ignora_la_columna_de_cedula_de_archivos_anteriores(): void
    {
        $this->importar("Nombre;Cédula;Área;Correo\nLuis;1001;TI;luis@empresa.com\n")
            ->assertSessionHas('importacion', fn ($r) => $r['creados'] === 1 && $r['errores'] === []);

        $this->assertNull(Participante::where('correo', 'luis@empresa.com')->value('documento'));
    }

    public function test_la_plantilla_y_la_exportacion_se_descargan(): void
    {
        Participante::create(['nombre' => 'Ana', 'correo' => 'Ana@Empresa.com', 'codigo_acceso' => 'ABC234']);

        $plantilla = $this->get(route('dashboard.participantes.plantilla'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Nombre;Área;Correo', $plantilla);

        $exportado = $this->get(route('dashboard.participantes.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Ana;;ana@empresa.com;Sí', $exportado);
    }

    public function test_rechaza_archivos_que_no_son_csv(): void
    {
        $this->post(route('dashboard.participantes.importar'), [
            'archivo' => UploadedFile::fake()->create('lista.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ])->assertSessionHasErrors('archivo');

        $this->assertSame(0, Participante::count());
    }
}
