<?php

namespace App\Http\Controllers\Juego;

use App\Http\Controllers\Controller;
use App\Services\Juego;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Top 3: pantalla pública para proyectar en la sala. Primero el podio
 * general, con todos los jugadores sin importar el dominio de su correo;
 * después un podio por cada dominio que tenga jugadores en el ranking. Los
 * dominios permitidos (Parámetros › Dominios permitidos) van primero, en ese
 * orden; los demás, en orden alfabético.
 */
class TopController extends Controller
{
    private const PUESTOS = 3;

    /** Partidas del ranking que se consideran para armar los podios. */
    private const LIMITE = 5000;

    public function __construct(private Juego $juego) {}

    public function index()
    {
        $podios = $this->podios();

        return view('game.top', ['podios' => $podios, 'firma' => $this->firma($podios)]);
    }

    /**
     * Solo los podios, ya pintados: la pantalla los consulta cada cierto
     * tiempo y los repinta si la firma (X-Firma) cambió.
     */
    public function podiosHtml(): Response
    {
        $podios = $this->podios();

        return response()
            ->view('partials.podios', ['podios' => $podios])
            ->header('X-Firma', $this->firma($podios));
    }

    private function firma(array $podios): string
    {
        return md5(json_encode($podios));
    }

    /** @return array<int, array{dominio: ?string, puestos: array<int, ?array<string, mixed>>}> */
    private function podios(): array
    {
        $permitidos = preg_split('/[\s,;]+/', mb_strtolower((string) ajuste('dominios_outlook', '')), -1, PREG_SPLIT_NO_EMPTY);
        $permitidos = array_values(array_unique(array_map(fn (string $dominio): string => ltrim($dominio, '@'), $permitidos)));

        // Un solo recorrido del ranking: ya viene ordenado, así que cada grupo conserva el orden.
        $ranking = $this->juego->ranking(self::LIMITE);

        $porDominio = $ranking
            ->groupBy(fn (array $fila): string => mb_strtolower((string) Str::after((string) $fila['partida']->participante->correo, '@')))
            ->sortBy(function ($filas, string $dominio) use ($permitidos): string {
                $posicion = array_search($dominio, $permitidos, true);

                return $posicion === false ? '1'.$dominio : '0'.str_pad((string) $posicion, 4, '0', STR_PAD_LEFT);
            });

        $podios = collect([['dominio' => null, 'filas' => $ranking]]);

        // Con un único dominio, su podio sería igual al general.
        if ($porDominio->count() > 1) {
            $porDominio->each(fn ($filas, string $dominio) => $podios->push(['dominio' => $dominio, 'filas' => $filas]));
        }

        // Siempre tres lugares: los vacíos se muestran como «por definir».
        return $podios->map(fn (array $podio): array => [
            'dominio' => $podio['dominio'],
            'puestos' => array_pad($this->puestos($podio['filas']), self::PUESTOS, null),
        ])->values()->all();
    }

    /** Los tres primeros del grupo, numerados dentro de él. */
    private function puestos($filas): array
    {
        return $filas->take(self::PUESTOS)->values()->map(fn (array $fila, int $i): array => [
            'puesto' => $i + 1,
            'nombre' => $fila['nombre'],
            'area' => $fila['area'],
            'aciertos' => $fila['aciertos'],
            'total' => $fila['total'],
            'tiempo' => $fila['tiempo'],
        ])->all();
    }
}
