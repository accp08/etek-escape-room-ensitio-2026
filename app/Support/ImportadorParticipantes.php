<?php

namespace App\Support;

use App\Models\Participante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Carga masiva de participantes desde un CSV (Excel → Guardar como CSV).
 *
 * Tolera lo que suele producir Excel en español: separador «;», codificación
 * Windows-1252 y BOM. Los encabezados se reconocen por nombre y sin importar
 * tildes ni mayúsculas; si el archivo no trae encabezados, las columnas se
 * leen en el orden de la plantilla: nombre, área, correo.
 *
 * El correo es lo único con lo que se entra al juego: es obligatorio, no
 * puede repetirse y es lo que identifica a un participante ya cargado. Una
 * columna de cédula que venga en archivos viejos simplemente se ignora.
 */
class ImportadorParticipantes
{
    public const COLUMNAS = ['nombre', 'area', 'correo'];

    /** Nombres de encabezado aceptados (ya normalizados) para cada columna. */
    private const ALIAS = [
        'nombre' => ['nombre', 'nombres', 'nombre completo', 'nombres y apellidos', 'participante'],
        'area' => ['area', 'dependencia', 'departamento', 'cargo'],
        'correo' => ['correo', 'email', 'e-mail', 'correo electronico', 'mail'],
    ];

    public const MAXIMO_FILAS = 5000;

    /**
     * @return array{creados:int, actualizados:int, omitidos:int, errores:array<int, string>}
     */
    public function importar(string $contenido, bool $actualizarExistentes): array
    {
        $reporte = ['creados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => []];

        $filas = $this->leer($contenido);

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

        DB::transaction(function () use ($filas, $mapa, $inicio, $actualizarExistentes, &$reporte, &$correos) {
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

                $datos['correo'] = mb_strtolower($datos['correo']);

                if (isset($correos[$datos['correo']])) {
                    $reporte['errores'][$linea] = "El correo {$datos['correo']} está repetido en el archivo (fila {$correos[$datos['correo']]}).";

                    continue;
                }

                $correos[$datos['correo']] = $linea;

                $datos['area'] = $datos['area'] !== '' ? $datos['area'] : null;

                $existente = Participante::where('correo', $datos['correo'])->first();

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
                ]);
                $reporte['creados']++;
            }
        });

        return $reporte;
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
