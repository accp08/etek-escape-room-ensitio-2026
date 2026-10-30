<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Mision;
use App\Models\Participante;
use App\Models\PartidaMision;
use App\Models\Registro;
use App\Services\Estadisticas;
use App\Services\Juego;
use App\Support\Xlsx;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class PanelController extends Controller
{
    /** Dashboard general. */
    public function index(Estadisticas $estadisticas, Juego $juego)
    {
        return view('dashboard.panel', [
            'resumen' => $estadisticas->resumen(),
            'porMision' => $estadisticas->desempenoPorMision(),
            'porDia' => $estadisticas->partidasPorDia(),
            'actividad' => $estadisticas->actividadReciente(),
            'pulso' => $estadisticas->pulsoBitacora(),
            'ranking' => $juego->ranking(5),
            'enLinea' => $estadisticas->jugadoresEnLinea(),
        ]);
    }

    /** Fragmento «Jugadores en línea», que el dashboard vuelve a pedir cada pocos segundos. */
    public function enLinea(Estadisticas $estadisticas)
    {
        return view('dashboard.en-linea', ['enLinea' => $estadisticas->jugadoresEnLinea()]);
    }

    /** Resultados por temática, área, participante y misión. */
    public function reporteAreas(Request $peticion)
    {
        $tematicas = Mision::jugables()->get()->pluck('etiqueta')->filter()->unique()->sort()->values();
        $areas = Participante::query()->whereNotNull('area')->where('area', '<>', '')
            ->distinct()->orderBy('area')->pluck('area');
        $tematica = (string) $peticion->query('tematica', '');
        $area = (string) $peticion->query('area', '');

        $avances = $this->filasReporteAreas($tematica, $area);

        $rendimientoAreas = $avances->groupBy('area')->map(function ($filas, $nombre) {
            $posibles = $filas->sum('posibles');

            return [
                'area' => $nombre,
                'precision' => $posibles > 0 ? round($filas->sum('aciertos') * 100 / $posibles) : 0,
                'intentos' => $filas->sum('intentos'),
                'segundos' => $filas->sum('segundos'),
            ];
        })->sortByDesc('precision')->values();

        return view('dashboard.reporte-areas', [
            'reporte' => $avances,
            'tematicas' => $tematicas,
            'areas' => $areas,
            'tematica' => $tematica,
            'area' => $area,
            'areaMasFuerte' => $rendimientoAreas->first(),
            'areaMasDebil' => $rendimientoAreas->last(),
        ]);
    }

    /** Descarga en Excel las mismas filas que presenta el reporte filtrado. */
    public function exportarReporteAreas(Request $peticion): Response
    {
        $reporte = $this->filasReporteAreas(
            (string) $peticion->query('tematica', ''),
            (string) $peticion->query('area', ''),
        );
        $filas = $reporte->map(fn (array $fila) => [
            $fila['area'],
            $fila['participante'],
            $fila['tematica'],
            $fila['mision'],
            (string) $fila['intentos'],
            sprintf('%02d:%02d', intdiv($fila['segundos'], 60), $fila['segundos'] % 60),
            $fila['aciertos'].'/'.$fila['posibles'],
        ])->prepend([
            'Área', 'Participante', 'Temática', 'Misión', 'Intentos', 'Tiempo', 'Aciertos',
        ])->all();

        $nombre = 'reporte-areas-'.now()->format('Y-m-d-Hi').'.xlsx';

        return response(Xlsx::escribir([[
            'nombre' => 'Reporte por áreas',
            'encabezado' => true,
            'anchos' => [24, 32, 30, 36, 12, 12, 12],
            'filas' => $filas,
        ]]), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    /** @return Collection<int, array{participante: string, area: string, tematica: string, mision: string, intentos: int, segundos: int, aciertos: int, posibles: int}> */
    private function filasReporteAreas(string $tematica, string $area): Collection
    {
        return PartidaMision::query()
            ->with(['partida.participante', 'mision'])
            ->whereHas('mision', function ($consulta) use ($tematica) {
                $consulta->where('visible', true)->where('jugable', true);
                if ($tematica !== '') {
                    $consulta->where('etiqueta', $tematica);
                }
            })
            ->whereHas('partida.participante', function ($consulta) use ($area) {
                if ($area !== '') {
                    $consulta->where('area', $area);
                }
            })
            ->get()
            ->filter(fn (PartidaMision $avance) => $avance->partida?->participante !== null)
            ->groupBy(fn (PartidaMision $avance) => implode(':', [
                $avance->partida->participante_id,
                $avance->mision_id,
            ]))
            ->map(function ($intentos) {
                $avance = $intentos->first();
                $participante = $avance->partida->participante;

                return [
                    'participante' => $participante->nombre,
                    'area' => $participante->area ?: 'Sin área',
                    'tematica' => $avance->mision->etiqueta ?: 'Sin temática',
                    'mision' => $avance->mision->titulo,
                    'intentos' => (int) $intentos->sum('intentos_usados'),
                    'segundos' => (int) $intentos->sum('segundos'),
                    'aciertos' => (int) $intentos->sum('aciertos'),
                    'posibles' => (int) $intentos->sum('aciertos_posibles'),
                ];
            })
            ->sortBy(['area', 'participante', 'mision'])
            ->values();
    }

    /** Bitácora completa, con filtros. */
    public function registros(Request $peticion)
    {
        $consulta = Registro::with(['usuario', 'participante'])->latest('id');

        if ($canal = $peticion->query('canal')) {
            $consulta->where('canal', $canal);
        }

        if ($nivel = $peticion->query('nivel')) {
            $consulta->where('nivel', $nivel);
        }

        if ($busqueda = trim((string) $peticion->query('q'))) {
            $consulta->where(function ($c) use ($busqueda) {
                $c->where('descripcion', 'like', "%{$busqueda}%")
                    ->orWhere('tipo', 'like', "%{$busqueda}%");
            });
        }

        return view('dashboard.registros', [
            'registros' => $consulta->paginate(40)->withQueryString(),
            'canal' => $canal,
            'nivel' => $nivel,
            'busqueda' => $busqueda,
            'totales' => [
                'todos' => Registro::count(),
                'juego' => Registro::where('canal', 'juego')->count(),
                'panel' => Registro::where('canal', 'dashboard')->count(),
                'alerta' => Registro::where('nivel', 'alerta')->count(),
            ],
        ]);
    }
}
