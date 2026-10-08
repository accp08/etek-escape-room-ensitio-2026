<?php

namespace App\Services;

use App\Models\Mision;
use App\Models\Participante;
use App\Models\Partida;
use App\Models\PartidaMision;
use App\Models\Registro;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Cifras que alimentan el dashboard general. */
class Estadisticas
{
    /** Tarjetas de cabecera. */
    public function resumen(): array
    {
        $partidas = Partida::query();
        $total = (clone $partidas)->count();
        $completas = (clone $partidas)->where('estado', 'completada')->count();

        $aciertos = (int) Partida::sum('aciertos');
        $posibles = (int) Partida::sum('aciertos_posibles');

        $tiempos = Partida::where('segundos_jugados', '>', 0)->pluck('segundos_jugados');
        $promedio = $tiempos->isNotEmpty() ? (int) round($tiempos->avg()) : 0;

        return [
            'participantes' => Participante::count(),
            'activos' => Participante::where('activo', true)->count(),
            'partidas' => $total,
            'completadas' => $completas,
            'tasa_exito' => $total > 0 ? round($completas * 100 / $total) : 0,
            'precision' => $posibles > 0 ? round($aciertos * 100 / $posibles) : 0,
            'tiempo_medio' => sprintf('%02d:%02d', intdiv($promedio, 60), $promedio % 60),
            'misiones' => Mision::jugables()->count(),
            'bloqueadas' => Mision::where('visible', true)->where('jugable', false)->count(),
        ];
    }

    /**
     * Desempeño por misión: porcentaje medio de acierto entre los intentos
     * registrados. Solo cuenta las misiones jugables.
     */
    public function desempenoPorMision(): Collection
    {
        return Mision::jugables()->get()->map(function (Mision $mision) {
            $avances = PartidaMision::where('mision_id', $mision->id)
                ->where('aciertos_posibles', '>', 0)
                ->get();

            $intentos = $avances->count();
            $superadas = $avances->filter(fn (PartidaMision $avance) => $avance->superada())->count();

            $precision = $intentos > 0
                ? round($avances->sum('aciertos') * 100 / max(1, $avances->sum('aciertos_posibles')))
                : 0;

            return [
                'mision' => $mision,
                'etiqueta' => $mision->etiqueta ?: $mision->titulo,
                'intentos' => $intentos,
                'superadas' => $superadas,
                'falladas' => $avances->where('estado', 'fallada')->count(),
                'precision' => $precision,
            ];
        });
    }

    /** Partidas iniciadas por día, para la serie temporal. */
    public function partidasPorDia(int $dias = 14): Collection
    {
        $desde = Carbon::today()->subDays($dias - 1);

        $conteo = Partida::where('created_at', '>=', $desde)
            ->get()
            ->groupBy(fn (Partida $p) => $p->created_at->toDateString())
            ->map->count();

        return collect(range(0, $dias - 1))->map(function (int $i) use ($desde, $conteo) {
            $fecha = $desde->copy()->addDays($i);

            return [
                'fecha' => $fecha,
                'etiqueta' => $fecha->locale('es')->isoFormat('D MMM'),
                'dia' => $fecha->locale('es')->isoFormat('dd'),
                'cantidad' => $conteo->get($fecha->toDateString(), 0),
            ];
        });
    }

    /** Actividad reciente para el panel lateral del dashboard. */
    public function actividadReciente(int $limite = 8): Collection
    {
        return Registro::with(['usuario', 'participante'])
            ->latest('id')
            ->limit($limite)
            ->get();
    }

    /** Cuántos registros por nivel en las últimas 24 horas. */
    public function pulsoBitacora(): array
    {
        $desde = now()->subDay();

        return [
            'info' => Registro::where('created_at', '>=', $desde)->where('nivel', 'info')->count(),
            'aviso' => Registro::where('created_at', '>=', $desde)->where('nivel', 'aviso')->count(),
            'alerta' => Registro::where('created_at', '>=', $desde)->where('nivel', 'alerta')->count(),
        ];
    }
}
