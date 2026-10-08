<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Mision;
use App\Models\Participante;
use App\Models\Partida;
use App\Services\Juego;
use App\Support\Bitacora;
use App\Support\ImportadorParticipantes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ParticipanteController extends Controller
{
    public function index()
    {
        return view('dashboard.participantes', [
            'participantes' => Participante::with(['partidas', 'codigos'])->orderBy('nombre')->get(),
            'misiones' => Mision::jugables()->get(),
        ]);
    }

    public function guardar(Request $peticion, ?Participante $participante = null): RedirectResponse
    {
        $id = $participante?->id;

        $peticion->merge(['correo' => mb_strtolower(trim((string) $peticion->input('correo')))]);

        // El correo es el usuario y la cédula la contraseña: ambos obligatorios y únicos.
        $datos = $peticion->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'documento' => ['required', 'string', 'max:40', 'unique:participantes,documento'.($id ? ",{$id}" : '')],
            'area' => ['nullable', 'string', 'max:80'],
            'correo' => ['required', 'email', 'max:160', 'unique:participantes,correo'.($id ? ",{$id}" : '')],
        ], [
            'correo.unique' => 'Ya hay otro participante con ese correo.',
        ], ['documento' => 'cédula', 'correo' => 'correo']);

        $datos['activo'] = $peticion->boolean('activo', true);

        if ($participante?->exists) {
            $participante->update($datos);
            $mensaje = 'Participante actualizado.';
        } else {
            $datos['codigo_acceso'] = Participante::generarCodigo();
            $participante = Participante::create($datos);
            $mensaje = "Participante creado. Entra con {$participante->correo} y su cédula como contraseña.";
        }

        Bitacora::dashboard('participante.guardado', $mensaje, [
            'documento' => $participante->documento,
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
            "Se eliminó a {$participante->nombre}", ['documento' => $participante->documento], 'aviso');

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
        $peticion->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'archivo.required' => 'Selecciona el archivo CSV con los participantes.',
            'archivo.mimes' => 'El archivo debe ser CSV. En Excel: Archivo → Guardar como → CSV UTF-8.',
            'archivo.max' => 'El archivo no puede pesar más de 2 MB.',
            'archivo.uploaded' => 'El archivo no se pudo subir: supera el límite del servidor.',
        ]);

        $reporte = $importador->importar(
            (string) file_get_contents($peticion->file('archivo')->getRealPath()),
            $peticion->boolean('actualizar'),
        );

        Bitacora::dashboard('participantes.importados',
            "Carga masiva: {$reporte['creados']} creados, {$reporte['actualizados']} actualizados, "
            ."{$reporte['omitidos']} omitidos, ".count($reporte['errores']).' con error',
            ['errores' => count($reporte['errores'])],
            $reporte['errores'] ? 'aviso' : 'info');

        return back()->with('importacion', $reporte);
    }

    /** Plantilla vacía para la carga masiva. */
    public function plantilla(): StreamedResponse
    {
        return $this->csv('plantilla-participantes.csv', [
            ['Nombre', 'Documento', 'Área', 'Correo'],
            ['Ana Pérez', '1020304050', 'Talento humano', 'ana.perez@empresa.com'],
        ]);
    }

    /** Todos los participantes, en el mismo formato de la plantilla (sirve para editar y volver a cargar). */
    public function exportarParticipantes(): StreamedResponse
    {
        $filas = Participante::orderBy('nombre')->get()->map(fn ($p) => [
            $p->nombre, $p->documento, $p->area, $p->correo, $p->activo ? 'Sí' : 'No',
        ]);

        return $this->csv('participantes-'.now()->format('Y-m-d-Hi').'.csv', [
            ['Nombre', 'Documento', 'Área', 'Correo', 'Activo'],
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
                'Puesto', 'Participante', 'Documento', 'Área', 'Aciertos', 'Posibles', 'Tiempo',
                'Partida', 'Certificado', 'Descargado el', 'Nº de descargas',
            ]);

            foreach ($filas as $f) {
                $partida = $f['partida'];

                fputcsv($salida, [
                    $f['puesto'],
                    $f['nombre'],
                    $partida->participante->documento,
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
                ]);
            }

            fclose($salida);
        }, 'ranking-'.now()->format('Y-m-d-Hi').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
