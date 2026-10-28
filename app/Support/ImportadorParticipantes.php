<?php

namespace App\Support;

use App\Models\CodigoMision;
use App\Models\Participante;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Carga masiva de participantes desde un Excel (.xlsx) o un CSV.
 *
 * Del CSV tolera lo que suele producir Excel en español: separador «;»,
 * codificación Windows-1252 y BOM. Los encabezados se reconocen por nombre y
 * sin importar tildes ni mayúsculas; si el archivo no trae encabezados, las
 * columnas se leen en el orden de la plantilla: nombre, área, correo, activo,
 * en ranking.
 *
 * Activo y En ranking son las dos casillas del participante en el dashboard:
 * se escriben Sí o No. Vacías, un participante nuevo queda con las dos
 * marcadas y uno existente conserva lo que tenía.
 *
 * El correo es lo único con lo que se entra al juego: es obligatorio, no
 * puede repetirse y es lo que identifica a un participante ya cargado. Una
 * columna de cédula que venga en archivos viejos simplemente se ignora.
 */
class ImportadorParticipantes
{
    public const COLUMNAS = ['nombre', 'area', 'correo', 'activo', 'en_ranking'];

    /** Las casillas del participante, con el nombre que se muestra en los errores. */
    public const CASILLAS = ['activo' => 'Activo', 'en_ranking' => 'En ranking'];

    /** Nombres de encabezado aceptados (ya normalizados) para cada columna. */
    private const ALIAS = [
        'nombre' => ['nombre', 'nombres', 'nombre completo', 'nombres y apellidos', 'participante'],
        'area' => ['area', 'dependencia', 'departamento', 'cargo'],
        'correo' => ['correo', 'email', 'e-mail', 'correo electronico', 'mail'],
        'activo' => ['activo', 'activa', 'habilitado', 'puede jugar'],
        'en_ranking' => ['en ranking', 'ranking', 'en el ranking', 'aparece en el ranking'],
    ];

    public const MAXIMO_FILAS = 5000;

    /**
     * Filas del archivo subido según su extensión.
     *
     * @return array<int, array<int, string>>
     */
    public function leerArchivo(string $ruta, string $extension): array
    {
        if (strtolower($extension) === 'xlsx') {
            return Xlsx::leer($ruta) ?? [];
        }

        return $this->leer((string) file_get_contents($ruta));
    }

    /**
     * @param  array<int, array<int, string>>  $filas
     * @return array{creados:int, actualizados:int, omitidos:int, errores:array<int, string>}
     */
    public function importar(array $filas, bool $actualizarExistentes): array
    {
        $reporte = ['creados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => []];

        if ($filas === []) {
            $reporte['errores'][0] = 'El archivo está vacío o no tiene filas legibles.';

            return $reporte;
        }

        [$mapa, $inicio] = $this->mapaColumnas($filas[0]);

        if (count($filas) - $inicio > self::MAXIMO_FILAS) {
            $reporte['errores'][0] = 'El archivo supera el máximo de '.self::MAXIMO_FILAS.' filas. Divídelo en varios.';

            return $reporte;
        }

        $correos = [];

        // Una sola consulta en vez de una por fila: con 2.000 filas se nota.
        $existentes = Participante::all()->keyBy('correo');

        try {
            DB::transaction(function () use ($filas, $mapa, $inicio, $actualizarExistentes, $existentes, &$reporte, &$correos) {
                $this->procesar($filas, $mapa, $inicio, $actualizarExistentes, $existentes, $reporte, $correos);
            });
        } catch (RuntimeException) {
            // Se acabaron los fragmentos únicos por misión: la transacción ya se deshizo.
            $capacidad = strlen(CodigoMision::ALFABETO) ** CodigoMision::largo();

            return ['creados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => [
                0 => 'No se cargó ningún participante: con fragmentos de '.CodigoMision::largo()
                    .' caracteres caben máximo '.number_format($capacidad, 0, ',', '.').' participantes en total. '
                    .'Hay que ampliar el largo del fragmento (largo_fragmento) antes de cargar esta lista.',
            ]];
        }

        return $reporte;
    }

    /** @param  Collection<string, Participante>  $existentes  por correo */
    private function procesar(array $filas, array $mapa, int $inicio, bool $actualizarExistentes, Collection $existentes, array &$reporte, array &$correos): void
    {
        for ($i = $inicio; $i < count($filas); $i++) {
            $linea = $i + 1;   // como la ve el usuario en Excel
            $datos = [];

            foreach ($mapa as $columna => $posicion) {
                $datos[$columna] = trim((string) ($filas[$i][$posicion] ?? ''));
            }

            // Filas totalmente vacías (muy comunes al final de un Excel).
            if (implode('', $datos) === '') {
                continue;
            }

            $casillas = [];
            foreach (array_keys(self::CASILLAS) as $columna) {
                $casillas[$columna] = $this->casilla($datos[$columna] ?? '');
                unset($datos[$columna]);
            }

            $validador = Validator::make($datos, [
                'nombre' => ['required', 'string', 'max:120'],
                'area' => ['nullable', 'string', 'max:80'],
                'correo' => ['required', 'email', 'max:160'],
            ], [
                'correo.required' => 'Falta el correo: es el usuario con el que entra al juego.',
            ], ['area' => 'área', 'correo' => 'correo']);

            if ($validador->fails()) {
                $reporte['errores'][$linea] = $validador->errors()->first();

                continue;
            }

            if ($invalida = array_search('invalido', $casillas, true)) {
                $reporte['errores'][$linea] = 'La columna '.self::CASILLAS[$invalida]
                    ." debe ser Sí o No (vino «{$filas[$i][$mapa[$invalida]]}»).";

                continue;
            }

            // Las casillas vacías no pisan lo que ya tenía un participante existente.
            $datos += array_filter($casillas, fn ($v) => $v !== null);

            $datos['correo'] = mb_strtolower($datos['correo']);

            if (isset($correos[$datos['correo']])) {
                $reporte['errores'][$linea] = "El correo {$datos['correo']} está repetido en el archivo (fila {$correos[$datos['correo']]}).";

                continue;
            }

            $correos[$datos['correo']] = $linea;

            // Sin columna de área en el archivo, no se toca la que ya tenía.
            if (array_key_exists('area', $datos)) {
                $datos['area'] = $datos['area'] !== '' ? $datos['area'] : null;
            }

            $existente = $existentes->get($datos['correo']);

            if ($existente) {
                if ($actualizarExistentes) {
                    $existente->update($datos);
                    $reporte['actualizados']++;
                } else {
                    $reporte['omitidos']++;
                }

                continue;
            }

            Participante::create($datos + [
                'codigo_acceso' => Participante::generarCodigo(),
                'activo' => true,
                'en_ranking' => true,
            ]);
            $reporte['creados']++;
        }
    }

    /** Sí / No de una casilla: null si la celda viene vacía, 'invalido' si no se entiende. */
    private function casilla(string $valor): bool|string|null
    {
        $normal = Str::of($valor)->ascii()->lower()->trim()->toString();

        return match (true) {
            $normal === '' => null,
            in_array($normal, ['si', 's', 'x', '1', 'true', 'verdadero', 'yes', 'y'], true) => true,
            in_array($normal, ['no', 'n', '0', 'false', 'falso'], true) => false,
            default => 'invalido',
        };
    }

    /** @return array<int, array<int, string>> */
    private function leer(string $contenido): array
    {
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);

        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        $contenido = str_replace(["\r\n", "\r"], "\n", $contenido);
        $primera = strtok($contenido, "\n") ?: '';

        // El separador es el que más aparece en la primera línea.
        $separador = collect([';', ',', "\t", '|'])
            ->sortByDesc(fn ($s) => substr_count($primera, $s))
            ->first();

        $flujo = fopen('php://temp', 'r+');
        fwrite($flujo, $contenido);
        rewind($flujo);

        $filas = [];
        while (($fila = fgetcsv($flujo, 0, $separador, '"', '')) !== false) {
            if ($fila === [null]) {
                continue;   // línea en blanco
            }
            $filas[] = array_map(fn ($v) => (string) $v, $fila);
        }
        fclose($flujo);

        return $filas;
    }

    /**
     * Posición de cada columna y fila donde empiezan los datos.
     *
     * @return array{0: array<string, int>, 1: int}
     */
    private function mapaColumnas(array $encabezado): array
    {
        $mapa = [];

        foreach ($encabezado as $posicion => $titulo) {
            $normal = Str::of($titulo)->ascii()->lower()->replaceMatches('/[^a-z0-9 -]/', '')->squish()->toString();

            foreach (self::ALIAS as $columna => $alias) {
                if (! isset($mapa[$columna]) && in_array($normal, $alias, true)) {
                    $mapa[$columna] = $posicion;
                }
            }
        }

        // Con nombre y correo reconocidos, la primera fila es el encabezado.
        if (isset($mapa['nombre'], $mapa['correo'])) {
            return [$mapa, 1];
        }

        return [array_flip(self::COLUMNAS), 0];
    }
}
