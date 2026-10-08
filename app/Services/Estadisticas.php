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
    /** Minutos sin actividad tras los cuales un participante deja de contar como «en línea». */
    public const MINUTOS_EN_LINEA = 2;

    /** Pantallas del juego que no son misiones, con el nombre que se muestra. */
    private const PANTALLAS = [
        'hub' => 'Centro de mando',
        'instrucciones' => 'Instrucciones',
        'final' => 'Código final',
        'victoria' => 'Victoria',
        'ranking' => 'Ranking',
        'certificado' => 'Certificado',
    ];

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

    /**
     * Quién está jugando ahora y dónde. Un participante con el cronómetro de
     * una misión corriendo cuenta en esa misión aunque su celular esté en otra
     * pantalla: físicamente está en la estación.
     *
     * @return array{
     *     total: int,
     *     resolviendo: int,
     *     escenarios: Collection<int, array{nombre: string, cantidad: int, mision: bool}>,
     *     jugadores: Collection<int, array{nombre: string, area: ?string, lugar: string, resolviendo: bool, visto_en: Carbon}>
     * }
     */
    public function jugadoresEnLinea(): array
    {
        $conectados = Participante::where('activo', true)
            ->where('visto_en', '>=', now()->subMinutes(self::MINUTOS_EN_LINEA))
            ->orderByDesc('visto_en')
            ->get();

        $misiones = Mision::jugables()->get();

        // participante_id => misión con el cronómetro corriendo
        $enCurso = PartidaMision::with(['mision', 'partida'])
            ->where('estado', 'en_curso')
            ->whereNotNull('iniciada_en')
            ->whereHas('partida', fn ($c) => $c->whereIn('participante_id', $conectados->modelKeys()))
            ->get()
            ->reject(fn (PartidaMision $avance) => $avance->sinTiempo())
            ->mapWithKeys(fn (PartidaMision $avance) => [$avance->partida->participante_id => $avance->mision]);

        $jugadores = $conectados->map(function (Participante $participante) use ($enCurso, $misiones) {
            $mision = $enCurso->get($participante->id);
            $ubicacion = (string) $participante->ubicacion;

            $lugar = match (true) {
                $mision !== null => $mision->titulo,
                str_starts_with($ubicacion, 'mision:') => $misiones->firstWhere('slug', substr($ubicacion, 7))?->titulo ?? 'Misión',
                default => self::PANTALLAS[$ubicacion] ?? 'Juego',
            };

            return [
                'nombre' => $participante->nombre,
                'area' => $participante->area,
                'lugar' => $lugar,
                'resolviendo' => $mision !== null,
                'visto_en' => $participante->visto_en,
            ];
        });

        $porLugar = $jugadores->countBy('lugar');

        $escenarios = $misiones
            ->map(fn (Mision $mision) => ['nombre' => $mision->titulo, 'mision' => true])
            ->push(['nombre' => self::PANTALLAS['hub'], 'mision' => false])
            ->concat(
                $porLugar->keys()
                    ->diff($misiones->pluck('titulo')->push(self::PANTALLAS['hub']))
                    ->map(fn (string $lugar) => ['nombre' => $lugar, 'mision' => false])
            )
            ->map(fn (array $escenario) => $escenario + ['cantidad' => $porLugar->get($escenario['nombre'], 0)])
            ->values();

        return [
            'total' => $jugadores->count(),
            'resolviendo' => $jugadores->where('resolviendo', true)->count(),
            'escenarios' => $escenarios,
            'jugadores' => $jugadores,
        ];
    }
}
