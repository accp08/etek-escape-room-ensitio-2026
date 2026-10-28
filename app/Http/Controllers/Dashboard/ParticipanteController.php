<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Mision;
use App\Models\Participante;
use App\Models\Partida;
use App\Services\Juego;
use App\Support\Bitacora;
use App\Support\ImportadorParticipantes;
use App\Support\Xlsx;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ParticipanteController extends Controller
{
    /** Filtros que acepta la lista, con sus opciones. */
    public const FILTROS = [
        'estado' => ['activos' => 'Activos', 'inactivos' => 'Inactivos'],
        'ranking' => ['dentro' => 'En el ranking', 'fuera' => 'Fuera del ranking'],
        'partida' => ['sin_partida' => 'Sin partida', 'en_curso' => 'En curso', 'completada' => 'Completada'],
    ];

    /**
     * Lista paginada: con 2.000 participantes, cargarlos todos con sus partidas
     * y fragmentos hacía la página pesadísima. Cada página trae solo su tramo
     * y la última partida de cada uno.
     */
    public function index(Request $peticion)
    {
        $busqueda = trim((string) $peticion->query('q'));
        $area = trim((string) $peticion->query('area'));
        $filtros = collect(self::FILTROS)
            ->map(fn ($opciones, $clave) => array_key_exists($valor = (string) $peticion->query($clave), $opciones) ? $valor : '');

        $consulta = Participante::with(['ultimaPartida', 'codigos'])->orderBy('nombre')->orderBy('id');

        if ($busqueda !== '') {
            $consulta->where(function ($c) use ($busqueda) {
                $c->where('nombre', 'like', "%{$busqueda}%")
                    ->orWhere('correo', 'like', "%{$busqueda}%")
                    ->orWhere('area', 'like', "%{$busqueda}%");
            });
        }

        if ($area !== '') {
            $consulta->where('area', $area);
        }

        match ($filtros['estado']) {
            'activos' => $consulta->where('activo', true),
            'inactivos' => $consulta->where('activo', false),
            default => null,
        };

        match ($filtros['ranking']) {
            'dentro' => $consulta->where('en_ranking', true),
            'fuera' => $consulta->where('en_ranking', false),
            default => null,
        };

        match ($filtros['partida']) {
            'sin_partida' => $consulta->whereDoesntHave('partidas'),
            'completada' => $consulta->whereHas('partidas', fn ($p) => $p->where('estado', 'completada')),
            'en_curso' => $consulta->whereHas('partidas')
                ->whereDoesntHave('partidas', fn ($p) => $p->where('estado', 'completada')),
            default => null,
        };

        return view('dashboard.participantes', [
            'participantes' => $consulta->paginate(25)->withQueryString(),
            'total' => Participante::count(),
            'areas' => Participante::whereNotNull('area')->distinct()->orderBy('area')->pluck('area'),
            'busqueda' => $busqueda,
            'area' => $area,
            'filtros' => $filtros,
            'filtrando' => $busqueda !== '' || $area !== '' || $filtros->filter()->isNotEmpty(),
            'misiones' => Mision::jugables()->get(),
        ]);
    }

    public function guardar(Request $peticion, ?Participante $participante = null): RedirectResponse
    {
        $id = $participante?->id;

        $peticion->merge(['correo' => mb_strtolower(trim((string) $peticion->input('correo')))]);

        // El correo es lo único con lo que se entra: obligatorio y único.
        $datos = $peticion->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:80'],
            'correo' => ['required', 'email', 'max:160', 'unique:participantes,correo'.($id ? ",{$id}" : '')],
        ], [
            'correo.unique' => 'Ya hay otro participante con ese correo.',
        ], ['correo' => 'correo']);

        $datos['activo'] = $peticion->boolean('activo', true);
        $datos['en_ranking'] = $peticion->boolean('en_ranking', true);

        if ($participante?->exists) {
            $participante->update($datos);
            $mensaje = 'Participante actualizado.';
        } else {
            $datos['codigo_acceso'] = Participante::generarCodigo();
            $participante = Participante::create($datos);
            $mensaje = "Participante creado. Entra con {$participante->correo}.";
        }

        Bitacora::dashboard('participante.guardado', $mensaje, [
            'correo' => $participante->correo,
        ]);

        return back()->with('exito', $mensaje);
    }

    public function nuevoCodigo(Participante $participante): RedirectResponse
    {
        $participante->update(['codigo_acceso' => Participante::generarCodigo()]);

        Bitacora::dashboard('participante.codigo', "Nuevo código para {$participante->nombre}");

        return back()->with('exito', "Nuevo código de {$participante->nombre}: {$participante->codigo_acceso}.");
    }

    public function borrar(Participante $participante): RedirectResponse
    {
        Bitacora::dashboard('participante.eliminado',
            "Se eliminó a {$participante->nombre}", ['correo' => $participante->correo], 'aviso');

        $participante->delete();   // arrastra sus partidas (cascade)

        return back()->with('exito', 'Participante eliminado junto con su partida.');
    }

    /** Borra la partida para que el participante pueda jugar desde cero. */
    public function reiniciar(Participante $participante): RedirectResponse
    {
        $participante->partidas()->delete();

        Bitacora::dashboard('partida.reiniciada',
            "Se reinició la partida de {$participante->nombre}", [], 'aviso');

        return back()->with('exito', "Se reinició la partida de {$participante->nombre}.");
    }

    /* ---------------- Carga masiva ---------------- */

    public function importar(Request $peticion, ImportadorParticipantes $importador): RedirectResponse
    {
        // Un .xlsx es un ZIP: según el servidor se detecta con uno u otro tipo.
        $peticion->validate([
            'archivo' => ['required', 'file', 'extensions:xlsx,csv,txt', 'mimes:xlsx,zip,csv,txt', 'max:5120'],
        ], [
            'archivo.required' => 'Selecciona el archivo de Excel (.xlsx) o CSV con los participantes.',
            'archivo.extensions' => 'El archivo debe ser Excel (.xlsx) o CSV. Si es un .xls viejo: Archivo → Guardar como → Libro de Excel (.xlsx).',
            'archivo.mimes' => 'El archivo debe ser Excel (.xlsx) o CSV. Si es un .xls viejo: Archivo → Guardar como → Libro de Excel (.xlsx).',
            'archivo.max' => 'El archivo no puede pesar más de 5 MB.',
            'archivo.uploaded' => 'El archivo no se pudo subir: supera el límite del servidor.',
        ]);

        // Cada participante nuevo genera sus fragmentos: 2.000 filas tardan más que el límite por defecto.
        @set_time_limit(300);

        $archivo = $peticion->file('archivo');

        $reporte = $importador->importar(
            $importador->leerArchivo($archivo->getRealPath(), $archivo->getClientOriginalExtension()),
            $peticion->boolean('actualizar'),
        );

        Bitacora::dashboard('participantes.importados',
            "Carga masiva: {$reporte['creados']} creados, {$reporte['actualizados']} actualizados, "
            ."{$reporte['omitidos']} omitidos, ".count($reporte['errores']).' con error',
            ['errores' => count($reporte['errores'])],
            $reporte['errores'] ? 'aviso' : 'info');

        return back()->with('importacion', $reporte);
    }

    /**
     * Plantilla de Excel para la carga masiva: la hoja Participantes trae solo
     * los encabezados (para no cargar ejemplos por error) y listas Sí/No en las
     * dos casillas; los ejemplos y las reglas van en la hoja Instrucciones.
     */
    public function plantilla(): Response
    {
        $filas = ImportadorParticipantes::MAXIMO_FILAS + 1;
        $ayuda = 'Sí o No. Vacío = Sí para los nuevos; a los existentes no se les cambia.';

        $contenido = Xlsx::escribir([
            [
                'nombre' => 'Participantes',
                'encabezado' => true,
                'anchos' => [34, 24, 38, 12, 14],
                'filas' => [['Nombre', 'Área', 'Correo', 'Activo', 'En ranking']],
                'listas' => [
                    "D2:D{$filas}" => ['valores' => ['Sí', 'No'], 'ayuda' => 'Activo: '.$ayuda],
                    "E2:E{$filas}" => ['valores' => ['Sí', 'No'], 'ayuda' => 'En ranking: '.$ayuda],
                ],
            ],
            [
                'nombre' => 'Instrucciones',
                'encabezado' => true,
                'anchos' => [16, 14, 90],
                'filas' => [
                    ['Columna', '¿Obligatoria?', 'Qué poner'],
                    ['Nombre', 'Sí', 'Nombre completo del participante (máx. 120 caracteres).'],
                    ['Área', 'No', 'Área o dependencia (máx. 80 caracteres).'],
                    ['Correo', 'Sí', 'Es el usuario con el que entra al juego. No puede repetirse.'],
                    ['Activo', 'No', 'Sí: puede entrar a jugar. No: queda bloqueado. Vacío: Sí para los nuevos; a los existentes no se les cambia.'],
                    ['En ranking', 'No', 'Sí: aparece en el ranking y en el top. No: juega pero no sale en el ranking. Vacío: igual que Activo.'],
                    [],
                    ['Reglas'],
                    ['', '', 'Llena la hoja Participantes desde la fila 2, una persona por fila. No cambies los encabezados.'],
                    ['', '', 'Máximo '.number_format(ImportadorParticipantes::MAXIMO_FILAS, 0, ',', '.').' filas por archivo.'],
                    ['', '', 'Los correos que ya existen se omiten, salvo que marques «Actualizar los que ya existen» al importar.'],
                    ['', '', 'Las filas con error no se cargan y se listan al final con su número de fila; el resto sí se carga.'],
                    [],
                    ['Ejemplo (no lo copies a la hoja Participantes)'],
                    ['Nombre', 'Área', 'Correo · Activo · En ranking'],
                    ['Ana Pérez', 'Talento humano', 'ana.perez@empresa.com · Sí · Sí'],
                    ['Luis Gómez', 'Gerencia', 'luis.gomez@empresa.com · Sí · No   (juega, pero no sale en el ranking)'],
                ],
            ],
        ]);

        return response($contenido, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="plantilla-participantes.xlsx"',
        ]);
    }

    /** Todos los participantes, en el mismo formato de la plantilla (sirve para editar y volver a cargar). */
    public function exportarParticipantes(): StreamedResponse
    {
        $filas = Participante::orderBy('nombre')->get()->map(fn ($p) => [
            $p->nombre, $p->area, $p->correo, $p->activo ? 'Sí' : 'No', $p->en_ranking ? 'Sí' : 'No',
        ]);

        return $this->csv('participantes-'.now()->format('Y-m-d-Hi').'.csv', [
            ['Nombre', 'Área', 'Correo', 'Activo', 'En ranking'],
            ...$filas,
        ]);
    }

    /** CSV con «;» y BOM: es lo que Excel en español abre sin asistente. */
    private function csv(string $nombre, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($filas) {
            $salida = fopen('php://output', 'w');
            fprintf($salida, "\xEF\xBB\xBF");

            foreach ($filas as $fila) {
                fputcsv($salida, $fila, ';', '"', '');
            }

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ---------------- Resultados ---------------- */

    public function resultados(Juego $juego, Request $peticion)
    {
        $ranking = $juego->ranking(200);

        // Permite ver solo quienes descargaron el certificado, o solo quienes no.
        $filtro = $peticion->query('certificado');

        if (in_array($filtro, ['descargado', 'visto', 'pendiente'], true)) {
            $ranking = $ranking
                ->filter(fn ($f) => $f['partida']->estadoCertificado() === $filtro)
                ->values();
        }

        $todas = Partida::query();

        return view('dashboard.resultados', [
            'ranking' => $ranking,
            'filtro' => $filtro,
            'partidas' => Partida::with(['participante', 'misiones.mision'])->latest('id')->get(),
            'certificados' => [
                'descargado' => (clone $todas)->whereNotNull('certificado_descargado_en')->count(),
                'visto' => (clone $todas)->whereNull('certificado_descargado_en')
                    ->whereNotNull('certificado_visto_en')->count(),
                'pendiente' => (clone $todas)->whereNull('certificado_visto_en')->count(),
                'total' => (clone $todas)->count(),
            ],
        ]);
    }

    public function exportar(Juego $juego): StreamedResponse
    {
        $filas = $juego->ranking(1000);

        return response()->streamDownload(function () use ($filas) {
            $salida = fopen('php://output', 'w');

            fprintf($salida, "\xEF\xBB\xBF");   // BOM para que Excel lea las tildes
            fputcsv($salida, [
                'Puesto', 'Participante', 'Correo', 'Área', 'Aciertos', 'Posibles', 'Tiempo',
                'Partida', 'Certificado', 'Descargado el', 'Nº de descargas',
            ], ';', '"', '');

            foreach ($filas as $f) {
                $partida = $f['partida'];

                fputcsv($salida, [
                    $f['puesto'],
                    $f['nombre'],
                    $partida->participante->correo,
                    $f['area'],
                    $f['aciertos'],
                    $f['total'],
                    $f['tiempo'],
                    $partida->completada() ? 'Completada' : 'En curso',
                    match ($partida->estadoCertificado()) {
                        'descargado' => 'Descargado',
                        'visto' => 'Solo lo vio',
                        default => 'No lo abrió',
                    },
                    $partida->certificado_descargado_en?->format('d/m/Y H:i') ?? '',
                    $partida->certificado_descargas,
                ], ';', '"', '');
            }

            fclose($salida);
        }, 'ranking-'.now()->format('Y-m-d-Hi').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
