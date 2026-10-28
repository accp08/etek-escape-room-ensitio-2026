<?php

namespace Tests\Feature;

use App\Models\CodigoMision;
use App\Models\Mision;
use App\Models\Participante;
use App\Models\User;
use App\Support\Xlsx;
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

    private function importar(string $contenido, bool $actualizar = false, string $nombre = 'participantes.csv')
    {
        return $this->post(route('dashboard.participantes.importar'), [
            'archivo' => UploadedFile::fake()->createWithContent($nombre, $contenido),
            'actualizar' => $actualizar ? '1' : null,
        ]);
    }

    /** Las 5 misiones del juego: cada participante nuevo genera un fragmento por cada una. */
    private function misiones(): void
    {
        foreach (range(1, 5) as $orden) {
            Mision::create(['slug' => "m{$orden}", 'titulo' => "M{$orden}", 'etiqueta' => "M{$orden}",
                'codigo_presencial' => "COD{$orden}", 'orden' => $orden]);
        }
    }

    private function excel(array $filas): string
    {
        return Xlsx::escribir([['nombre' => 'Participantes', 'encabezado' => true, 'filas' => $filas]]);
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

        $respuesta = $this->get(route('dashboard.participantes.plantilla'))->assertOk();
        $this->assertStringContainsString('spreadsheetml', $respuesta->headers->get('Content-Type'));

        $ruta = tempnam(sys_get_temp_dir(), 'pl');
        file_put_contents($ruta, $respuesta->getContent());
        // Solo encabezados: los ejemplos van en la hoja de instrucciones, no se cargan por error.
        $this->assertSame([['Nombre', 'Área', 'Correo', 'Activo', 'En ranking']], Xlsx::leer($ruta));
        $zip = new \ZipArchive;
        $zip->open($ruta);
        $this->assertStringContainsString('<formula1>"Sí,No"</formula1>', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        unlink($ruta);

        $exportado = $this->get(route('dashboard.participantes.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Ana;;ana@empresa.com;Sí', $exportado);
    }

    public function test_rechaza_archivos_que_no_son_excel_ni_csv(): void
    {
        $this->post(route('dashboard.participantes.importar'), [
            'archivo' => UploadedFile::fake()->create('lista.xls', 10, 'application/vnd.ms-excel'),
        ])->assertSessionHasErrors('archivo');

        $this->post(route('dashboard.participantes.importar'), [
            'archivo' => UploadedFile::fake()->create('lista.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('archivo');

        $this->assertSame(0, Participante::count());
    }

    public function test_importa_un_excel_con_las_casillas_activo_y_en_ranking(): void
    {
        $xlsx = $this->excel([
            ['Nombre', 'Área', 'Correo', 'Activo', 'En ranking'],
            ['Ana', 'TI', 'ana@empresa.com', 'Sí', 'Sí'],
            ['Luis', '', 'luis@empresa.com', 'si', 'No'],
            ['Eva', 'Legal', 'eva@empresa.com', 'No'],     // En ranking vacío → Sí
            ['Juan', 'TI', 'juan@empresa.com'],             // ambas vacías → Sí
            ['Mal', 'TI', 'mal@empresa.com', 'Tal vez'],
        ]);

        $this->importar($xlsx, nombre: 'participantes.xlsx')
            ->assertSessionHas('importacion', fn ($r) => $r['creados'] === 4 && array_keys($r['errores']) === [6]
                && str_contains($r['errores'][6], 'Activo debe ser Sí o No'));

        $estado = Participante::orderBy('id')->get()->mapWithKeys(fn ($p) => [$p->correo => [$p->activo, $p->en_ranking]]);
        $this->assertSame([
            'ana@empresa.com' => [true, true],
            'luis@empresa.com' => [true, false],
            'eva@empresa.com' => [false, true],
            'juan@empresa.com' => [true, true],
        ], $estado->all());
        $this->assertNull(Participante::where('correo', 'luis@empresa.com')->value('area'));
    }

    public function test_al_actualizar_las_casillas_vacias_conservan_lo_que_tenia(): void
    {
        $ana = Participante::create(['nombre' => 'Ana', 'correo' => 'ana@empresa.com', 'codigo_acceso' => 'ABC234', 'activo' => false, 'en_ranking' => false]);
        $luis = Participante::create(['nombre' => 'Luis', 'correo' => 'luis@empresa.com', 'codigo_acceso' => 'ABC235']);

        $this->importar("Nombre;Área;Correo;Activo;En ranking\nAna B;TI;ana@empresa.com;;\nLuis;TI;luis@empresa.com;No;No\n", actualizar: true)
            ->assertSessionHas('importacion', fn ($r) => $r['actualizados'] === 2 && $r['errores'] === []);

        $ana->refresh();
        $luis->refresh();
        $this->assertSame(['Ana B', false, false], [$ana->nombre, $ana->activo, $ana->en_ranking]);
        $this->assertSame([false, false], [$luis->activo, $luis->en_ranking]);
    }

    public function test_la_exportacion_se_puede_volver_a_cargar_con_sus_casillas(): void
    {
        Participante::create(['nombre' => 'Ana', 'correo' => 'ana@empresa.com', 'codigo_acceso' => 'ABC234', 'en_ranking' => false]);

        $exportado = $this->get(route('dashboard.participantes.csv'))->streamedContent();
        Participante::query()->delete();

        $this->importar($exportado)->assertSessionHas('importacion', fn ($r) => $r['creados'] === 1);
        $this->assertFalse(Participante::where('correo', 'ana@empresa.com')->value('en_ranking'));
    }

    public function test_carga_dos_mil_participantes_desde_excel(): void
    {
        config(['escape_room.largo_fragmento' => 3]);
        $this->misiones();

        $filas = [['Nombre', 'Área', 'Correo', 'Activo', 'En ranking']];
        for ($i = 1; $i <= 2000; $i++) {
            $filas[] = ["Persona {$i}", 'Área '.($i % 7), "persona{$i}@empresa.com", 'Sí', $i % 10 ? 'Sí' : 'No'];
        }

        $this->importar($this->excel($filas), nombre: 'participantes.xlsx')
            ->assertSessionHas('importacion', fn ($r) => $r['creados'] === 2000 && $r['errores'] === []);

        $this->assertSame(200, Participante::where('en_ranking', false)->count());
        $this->assertSame(10000, CodigoMision::count());
    }

    public function test_si_no_alcanzan_los_fragmentos_no_carga_a_nadie(): void
    {
        config(['escape_room.largo_fragmento' => 1]);   // 32 fragmentos por misión
        $this->misiones();

        $csv = "Nombre;Correo\n";
        for ($i = 1; $i <= 40; $i++) {
            $csv .= "P{$i};p{$i}@empresa.com\n";
        }

        $this->importar($csv)->assertSessionHas('importacion', fn ($r) => $r['creados'] === 0
            && str_contains($r['errores'][0], 'caben máximo 32 participantes'));

        $this->assertSame(0, Participante::count());
    }
}
