<?php

namespace App\Http\Controllers\Juego;

use App\Http\Controllers\Controller;
use App\Services\Juego;
use Illuminate\Http\Response;

/**
 * Top 3: pantalla pública para proyectar en la sala con un podio por cada
 * dominio permitido (Parámetros › Dominios permitidos) que ya tenga
 * jugadores en el ranking. Si no hay dominios configurados, o ninguno tiene
 * jugadores todavía, muestra un único podio general.
 */
class TopController extends Controller
{
    private const PUESTOS = 3;

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
        $dominios = preg_split('/[\s,;]+/', mb_strtolower((string) ajuste('dominios_outlook', '')), -1, PREG_SPLIT_NO_EMPTY);
        $dominios = array_values(array_unique(array_map(fn (string $dominio): string => ltrim($dominio, '@'), $dominios)));

        $podios = collect($dominios ?: [null])->map(function (?string $dominio): array {
            $filas = $this->juego->ranking(self::PUESTOS, $dominio)->map(fn (array $fila): array => [
                'puesto' => $fila['puesto'],
                'nombre' => $fila['nombre'],
                'area' => $fila['area'],
                'aciertos' => $fila['aciertos'],
                'total' => $fila['total'],
                'tiempo' => $fila['tiempo'],
            ])->all();

            return ['dominio' => $dominio, 'filas' => $filas];
        });

        // Los dominios sin jugadores no se muestran; si ninguno tiene, queda un podio general vacío.
        $conJugadores = $podios->filter(fn (array $podio): bool => $podio['filas'] !== [])
            ->whenEmpty(fn () => collect([['dominio' => null, 'filas' => []]]));

        // Siempre tres lugares: los vacíos se muestran como «por definir».
        return $conJugadores->map(fn (array $podio): array => [
            'dominio' => $podio['dominio'],
            'puestos' => array_pad($podio['filas'], self::PUESTOS, null),
        ])->values()->all();
    }
}
