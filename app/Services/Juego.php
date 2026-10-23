<?php

namespace App\Services;

use App\Models\CodigoMision;
use App\Models\Mision;
use App\Models\Participante;
use App\Models\Partida;
use App\Models\PartidaMision;
use Illuminate\Support\Collection;

/**
 * Ciclo de vida de una partida.
 *
 * Reglas acordadas:
 *  - Cada misión se resuelve en una estación de la sala. Escanear su QR
 *    arranca el cronómetro de la oportunidad; se supera con el código que
 *    entrega la estación.
 *  - Cada misión tiene N oportunidades (0 = ilimitadas). Cada oportunidad
 *    tiene su propio cronómetro y el tiempo de todas se suma al total.
 *  - Agotar el cronómetro consume la oportunidad, igual que fallar la respuesta.
 *  - Sin oportunidades, la misión queda desactivada y el participante sigue con las otras.
 *  - Cuando ya no le quedan misiones por jugar, las falladas se reactivan
 *    UNA sola vez (segunda vuelta).
 *  - El cronómetro lo lleva el servidor: recargar la página no regala tiempo.
 */
class Juego
{
    /* ------------------------------------------------------------------ */
    /* Partida */
    /* ------------------------------------------------------------------ */

    /**
     * La partida del participante. Se juega una sola vez: si ya existe —aunque
     * esté completada— se devuelve esa, nunca se crea una nueva encima.
     */
    public function partidaDe(Participante $participante): Partida
    {
        $partida = $participante->partidas()->latest('id')->first();

        if (! $partida) {
            return $this->crearPartida($participante);
        }

        $this->incorporarMisionesNuevas($partida);

        return $partida;
    }

    /**
     * Si el administrador habilita una misión mientras hay partidas en curso,
     * se agrega a esas partidas: de lo contrario el participante la vería
     * bloqueada y nunca podría reunir el código completo.
     */
    private function incorporarMisionesNuevas(Partida $partida): void
    {
        if ($partida->completada()) {
            return;
        }

        $nuevas = Mision::jugables()
            ->whereNotIn('id', $partida->misiones()->pluck('mision_id'))
            ->get();

        if ($nuevas->isEmpty()) {
            return;
        }

        foreach ($nuevas as $mision) {
            PartidaMision::create([
                'partida_id' => $partida->id,
                'mision_id' => $mision->id,
                'estado' => 'pendiente',
                'aciertos_posibles' => Mision::ACIERTOS_POSIBLES,
            ]);
        }

        $partida->update([
            'aciertos_posibles' => (int) $partida->misiones()->sum('aciertos_posibles'),
        ]);
    }

    public function crearPartida(Participante $participante): Partida
    {
        $misiones = Mision::jugables()->get();

        $partida = Partida::create([
            'participante_id' => $participante->id,
            'estado' => 'en_curso',
            'iniciada_en' => now(),
            'aciertos_posibles' => $misiones->count() * Mision::ACIERTOS_POSIBLES,
        ]);

        foreach ($misiones as $mision) {
            PartidaMision::create([
                'partida_id' => $partida->id,
                'mision_id' => $mision->id,
                'estado' => 'pendiente',
                'aciertos_posibles' => Mision::ACIERTOS_POSIBLES,
            ]);
        }

        return $partida;
    }

    /* ------------------------------------------------------------------ */
    /* Estado */
    /* ------------------------------------------------------------------ */

    /** Estado de cada misión visible, para pintar el centro de mando. */
    public function tablero(Partida $partida): Collection
    {
        $this->cerrarVencidas($partida);
        $this->reactivarSiCorresponde($partida);

        $avances = $partida->misiones()->with('mision')->get()->keyBy('mision_id');
        $fragmentos = $partida->participante->asegurarCodigos();

        return Mision::visibles()->get()->map(function (Mision $mision) use ($avances, $fragmentos) {
            $avance = $avances->get($mision->id);

            return [
                'mision' => $mision,
                'avance' => $avance,
                'estado' => $this->estadoDe($mision, $avance),
                'fragmento' => $avance?->superada() ? $fragmentos->get($mision->id) : null,
            ];
        });
    }

    /** bloqueada | disponible | superada | fallada */
    private function estadoDe(Mision $mision, ?PartidaMision $avance): string
    {
        if (! $mision->jugable || ! $avance) {
            return 'bloqueada';
        }

        return match (true) {
            $avance->superada() => 'superada',
            $avance->fallada() => 'fallada',
            default => 'disponible',
        };
    }

    private function quedanMisionesAbiertas(Partida $partida): bool
    {
        return $partida->misiones()
            ->where(function ($consulta) {
                $consulta->whereIn('estado', ['pendiente', 'en_curso'])
                    ->orWhere(function ($consulta) {
                        $consulta->where('estado', 'superada')
                            ->where(function ($consulta) {
                                $consulta->where('aciertos_posibles', '<', 1)
                                    ->orWhereColumn('aciertos', '<>', 'aciertos_posibles');
                            });
                    });
            })
            ->exists();
    }

    /**
     * Consume la oportunidad de cualquier misión cuyo cronómetro ya venció.
     * Si no le quedan más, queda fallada.
     */
    public function cerrarVencidas(Partida $partida): void
    {
        $partida->misiones()
            ->with('mision')
            ->where('estado', 'en_curso')
            ->get()
            ->each(function (PartidaMision $avance) {
                if ($avance->sinTiempo()) {
                    $this->vencerIntento($avance);
                }
            });
    }

    /**
     * Segunda vuelta: si ya no quedan misiones por jugar y hay falladas que
     * nunca se reactivaron, se abren de nuevo (una sola vez cada una).
     */
    public function reactivarSiCorresponde(Partida $partida): bool
    {
        if ($this->quedanMisionesAbiertas($partida)) {
            return false;
        }

        $reactivables = $partida->misiones()
            ->where('estado', 'fallada')
            ->where('reactivada', false)
            ->get();

        if ($reactivables->isEmpty()) {
            $this->cerrarPartidaSiTermino($partida);

            return false;
        }

        foreach ($reactivables as $avance) {
            $avance->update([
                'estado' => 'pendiente',
                'reactivada' => true,
                'intentos_usados' => 0,
                'iniciada_en' => null,
                'finalizada_en' => null,
            ]);
        }

        return true;
    }

    public function cerrarPartidaSiTermino(Partida $partida): void
    {
        if ($this->quedanMisionesAbiertas($partida) || $partida->completada()) {
            return;
        }

        $partida->update([
            'estado' => 'completada',
            'finalizada_en' => now(),
            'aciertos' => (int) $partida->misiones()->sum('aciertos'),
            'segundos_jugados' => (int) $partida->misiones()->sum('segundos'),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Jugar una misión */
    /* ------------------------------------------------------------------ */

    public function avanceDe(Partida $partida, Mision $mision): ?PartidaMision
    {
        return $partida->misiones()->where('mision_id', $mision->id)->first();
    }

    /**
     * Arranca el cronómetro de la oportunidad si todavía no corre. Se llama
     * al escanear el QR de la estación; volver a escanearlo no lo reinicia.
     */
    public function comenzar(PartidaMision $avance): PartidaMision
    {
        if ($avance->estado === 'superada' && ! $avance->superada()) {
            $avance->update([
                'estado' => 'pendiente',
                'intentos_usados' => 0,
                'iniciada_en' => null,
                'finalizada_en' => null,
            ]);
            $avance->partida()->update(['estado' => 'en_curso', 'finalizada_en' => null]);
            $avance->refresh();
        }

        if ($avance->abierta() && ! $avance->iniciada_en) {
            $avance->update([
                'estado' => 'en_curso',
                'iniciada_en' => now(),
            ]);
        }

        return $avance->refresh();
    }

    /**
     * Evalúa el código que el participante obtuvo en la estación.
     *
     * @return array{resultado:string, aciertos:int, posibles:int, porcentaje:float}
     *                                                                               resultado: superada | reintento | fallada | tiempo
     */
    /**
     * @param  string|array<string, string>|null  $codigo  arreglo nivel => código en la misión de clasificación
     */
    public function responder(PartidaMision $avance, string|array|null $codigo): array
    {
        $mision = $avance->mision;

        // El tiempo manda: si venció, no se evalúa nada y la oportunidad se pierde.
        if ($avance->sinTiempo()) {
            $this->vencerIntento($avance);
            $this->reactivarSiCorresponde($avance->partida);

            return ['resultado' => 'tiempo', 'aciertos' => $avance->aciertos,
                'posibles' => $avance->aciertos_posibles, 'porcentaje' => 0.0];
        }

        $codigo = is_array($codigo)
            ? array_map(fn ($c) => mb_substr(trim((string) $c), 0, 60), $codigo)
            : mb_substr(trim((string) $codigo), 0, 60);
        $superada = $mision->codigoPresencialCoincide($codigo);

        $avance->increment('intentos_usados');
        $avance->refresh();

        // Se guarda lo que escribió, para revisarlo desde el dashboard.
        $avance->update([
            'aciertos' => $superada ? Mision::ACIERTOS_POSIBLES : 0,
            'aciertos_posibles' => Mision::ACIERTOS_POSIBLES,
            'respuestas' => ['codigo' => $codigo],
        ]);

        if ($superada) {
            $this->cerrarMision($avance, 'superada');
            $resultado = 'superada';
        } elseif ($avance->leQuedanIntentos()) {
            $this->terminarIntento($avance);   // la siguiente oportunidad arranca con el cronómetro completo
            $resultado = 'reintento';
        } else {
            $this->cerrarMision($avance, 'fallada');
            $resultado = 'fallada';
        }

        $this->reactivarSiCorresponde($avance->partida);

        return [
            'resultado' => $resultado,
            'aciertos' => $avance->aciertos,
            'posibles' => Mision::ACIERTOS_POSIBLES,
            'porcentaje' => $superada ? 100.0 : 0.0,
        ];
    }

    /** El cronómetro de la oportunidad llegó a cero: la consume. */
    private function vencerIntento(PartidaMision $avance): void
    {
        $avance->increment('intentos_usados');

        if ($avance->leQuedanIntentos()) {
            $this->terminarIntento($avance);
        } else {
            $this->cerrarMision($avance, 'fallada');
        }
    }

    /**
     * Suma el tiempo de la oportunidad en curso al total de la misión y
     * detiene el cronómetro. La misión sigue abierta para la siguiente.
     */
    private function terminarIntento(PartidaMision $avance): void
    {
        $avance->update([
            'segundos' => $avance->segundos + $avance->segundosDelIntento(),
            'iniciada_en' => null,
        ]);

        $this->actualizarTotales($avance->partida);
    }

    private function cerrarMision(PartidaMision $avance, string $estado): void
    {
        $avance->update([
            'estado' => $estado,
            'segundos' => $avance->segundos + $avance->segundosDelIntento(),
            'iniciada_en' => null,
            'finalizada_en' => now(),
        ]);

        $this->actualizarTotales($avance->partida);
    }

    private function actualizarTotales(Partida $partida): void
    {
        $partida->update([
            'aciertos' => (int) $partida->misiones()->sum('aciertos'),
            'aciertos_posibles' => (int) $partida->misiones()->sum('aciertos_posibles'),
            'segundos_jugados' => (int) $partida->misiones()->sum('segundos'),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Código de recuperación */
    /* ------------------------------------------------------------------ */

    /** Fragmentos ya conseguidos, en orden de misión. */
    public function codigoObtenido(Partida $partida): string
    {
        return $this->tablero($partida)
            ->filter(fn ($f) => $f['estado'] === 'superada')
            ->pluck('fragmento')
            ->implode('');
    }

    /**
     * El código completo que este participante debe ingresar al final: sus
     * propios fragmentos, así que el código de otro jugador no le sirve.
     */
    public function codigoCompleto(Participante $participante): string
    {
        $fragmentos = $participante->asegurarCodigos();

        return Mision::jugables()->pluck('id')
            ->map(fn (int $id) => $fragmentos->get($id))
            ->implode('');
    }

    public function codigoCorrecto(Participante $participante, string $intento): bool
    {
        $limpio = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $intento));

        return $limpio !== '' && $limpio === strtoupper($this->codigoCompleto($participante));
    }

    /**
     * Dueño de un código de recuperación. Cada fragmento es único por misión,
     * así que el primero basta para saber de quién es; luego se valida entero.
     * Así la consola de la sala reconoce al jugador sin que inicie sesión.
     */
    public function participantePorCodigo(?string $intento): ?Participante
    {
        $limpio = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $intento));
        $primera = Mision::jugables()->value('id');

        if ($limpio === '' || ! $primera) {
            return null;
        }

        $participante = CodigoMision::where('mision_id', $primera)
            ->where('fragmento', substr($limpio, 0, CodigoMision::largo()))
            ->first()?->participante;

        return $participante && $participante->activo && $this->codigoCorrecto($participante, $limpio)
            ? $participante
            : null;
    }

    /**
     * Registra que el jugador digitó su código en la consola. Devuelve true la
     * primera vez y false si ya lo había hecho.
     */
    public function registrarCodigoFinal(Partida $partida): bool
    {
        if ($partida->codigoIngresado()) {
            return false;
        }

        $this->cerrarPartidaSiTermino($partida);

        $partida->update([
            'estado' => 'completada',
            'finalizada_en' => $partida->finalizada_en ?? now(),
            'codigo_final_en' => now(),
        ]);

        return true;
    }

    /**
     * Partidas que pueden aparecer en el ranking: validaron su código en la
     * consola, tienen aciertos, no se terminaron en menos del tiempo mínimo
     * configurado en Parámetros (ranking_minutos_minimos) y su participante
     * no fue sacado del ranking desde el dashboard.
     */
    private function enRanking()
    {
        return Partida::whereNotNull('codigo_final_en')
            ->where('aciertos', '>', 0)
            ->where('segundos_jugados', '>=', $this->segundosMinimosRanking())
            ->whereHas('participante', fn ($p) => $p->where('en_ranking', true));
    }

    private function segundosMinimosRanking(): int
    {
        return max(0, (int) ajuste('ranking_minutos_minimos', 0)) * 60;
    }

    /** Puesto de la partida en el ranking general (null si aún no validó su código en la consola o no califica). */
    public function puestoDe(Partida $partida): ?int
    {
        if (! $partida->codigoIngresado() || $partida->aciertos < 1
            || $partida->segundos_jugados < $this->segundosMinimosRanking()
            || ! $partida->participante->en_ranking) {
            return null;
        }

        $mejores = $this->enRanking()
            ->where(function ($consulta) use ($partida) {
                $consulta->where('aciertos', '>', $partida->aciertos)
                    ->orWhere(fn ($c) => $c->where('aciertos', $partida->aciertos)->where('segundos_jugados', '<', $partida->segundos_jugados))
                    ->orWhere(fn ($c) => $c->where('aciertos', $partida->aciertos)->where('segundos_jugados', $partida->segundos_jugados)->where('id', '<', $partida->id));
            })
            ->count();

        return $mejores + 1;
    }

    /** Partidas que aparecen en el ranking. */
    public function totalRanking(): int
    {
        return $this->enRanking()->count();
    }

    /** Solo se puede ejecutar el código final cuando se superaron todas las misiones jugables. */
    public function todasLasMisionesSuperadas(Partida $partida): bool
    {
        $misiones = Mision::jugables()->pluck('id');

        if ($misiones->isEmpty()) {
            return false;
        }

        $superadas = $partida->misiones()
            ->whereIn('mision_id', $misiones)
            ->where('estado', 'superada')
            ->where('aciertos_posibles', '>', 0)
            ->whereColumn('aciertos', 'aciertos_posibles')
            ->count();

        return $superadas === $misiones->count();
    }

    /* ------------------------------------------------------------------ */
    /* Ranking */
    /* ------------------------------------------------------------------ */

    /**
     * Más aciertos primero; a igualdad de aciertos, menos tiempo. Solo entra
     * quien ya validó su código en la consola. Con $dominio, solo los
     * participantes cuyo correo es de ese dominio.
     */
    public function ranking(int $limite = 50, ?string $dominio = null): Collection
    {
        return $this->ordenarRanking(
            $this->enRanking()
                ->when($dominio, fn ($consulta) => $consulta->whereHas('participante', fn ($p) => $p
                    ->where('correo', 'like', '%@'.addcslashes($dominio, '%_\\')))),
            $limite,
        );
    }

    /**
     * Ranking general de /ranking: todos los que ya validaron su código en la
     * consola, de cualquier dominio y sin tope de puestos.
     */
    public function rankingGeneral(): Collection
    {
        return $this->ordenarRanking($this->enRanking());
    }

    private function ordenarRanking($consulta, ?int $limite = null): Collection
    {
        return $consulta
            ->with('participante')
            ->orderByDesc('aciertos')
            ->orderBy('segundos_jugados')
            ->orderBy('id')   // desempate estable: el que llegó primero
            ->when($limite, fn ($c) => $c->limit($limite))
            ->get()
            ->values()
            ->map(function (Partida $partida, int $i) {
                return [
                    'puesto' => $i + 1,
                    'partida' => $partida,
                    'nombre' => $partida->participante->nombre,
                    'area' => $partida->participante->area,
                    'aciertos' => $partida->aciertos,
                    'total' => $partida->aciertos_posibles,
                    'tiempo' => $partida->tiempoLegible(),
                ];
            });
    }
}
