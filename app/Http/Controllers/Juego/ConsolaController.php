<?php

namespace App\Http\Controllers\Juego;

use App\Http\Controllers\Controller;
use App\Models\CodigoMision;
use App\Models\Mision;
use App\Services\Juego;
use App\Support\Bitacora;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consola de recuperación: pantalla pública que se proyecta en la sala. El
 * jugador digita ahí el código que ve en su celular; el código lo identifica
 * (es único), así que no hace falta iniciar sesión. A un lado se ve el top 10.
 */
class ConsolaController extends Controller
{
    private const TOP = 10;

    public function __construct(private Juego $juego) {}

    public function index()
    {
        $misiones = Mision::jugables()->count();

        return view('game.consola', [
            'misiones' => $misiones,
            'largo' => CodigoMision::largo(),
            'ranking' => $this->top(),
        ]);
    }

    /** Top 10 vigente: la pantalla lo consulta cada cierto tiempo. */
    public function ranking(): JsonResponse
    {
        return response()->json(['ranking' => $this->top()]);
    }

    public function verificar(Request $peticion): JsonResponse
    {
        $peticion->validate(['codigo' => ['required', 'string', 'max:60']]);

        $participante = $this->juego->participantePorCodigo($peticion->input('codigo'));
        $partida = $participante ? $this->juego->partidaDe($participante) : null;

        if (! $partida || ! $this->juego->todasLasMisionesSuperadas($partida)) {
            Bitacora::juego('consola.incorrecto', 'Código incorrecto en la consola de recuperación', [], 'aviso');

            return response()->json(['resultado' => 'incorrecto'], 422);
        }

        $primeraVez = $this->juego->registrarCodigoFinal($partida);
        $partida->refresh();

        if ($primeraVez) {
            Bitacora::juego('partida.completada', "{$participante->nombre} digitó su código en la consola", [
                'aciertos' => $partida->aciertos,
                'segundos' => $partida->segundos_jugados,
            ], participanteId: $participante->id);
        }

        $puesto = $this->juego->puestoDe($partida);

        return response()->json([
            'resultado' => 'correcto',
            'repetido' => ! $primeraVez,
            'nombre' => $participante->nombre,
            'area' => $participante->area,
            'tiempo' => $partida->tiempoLegible(),
            'puesto' => $puesto,
            'total' => $this->juego->totalRanking(),
            'en_top' => $puesto !== null && $puesto <= self::TOP,
            'partida' => $partida->id,
            'ranking' => $this->top(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function top(): array
    {
        return $this->juego->ranking(self::TOP)->map(fn (array $fila) => [
            'puesto' => $fila['puesto'],
            'partida' => $fila['partida']->id,
            'nombre' => $fila['nombre'],
            'area' => $fila['area'],
            'aciertos' => $fila['aciertos'],
            'total' => $fila['total'],
            'tiempo' => $fila['tiempo'],
        ])->all();
    }
}
