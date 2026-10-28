<?php

namespace App\Support;

use SimpleXMLElement;
use ZipArchive;

/**
 * Lectura y escritura mínima de archivos de Excel (.xlsx) sin dependencias:
 * un .xlsx es un ZIP con XML adentro. Alcanza para la carga masiva de
 * participantes: solo texto, una fila de encabezado con estilo y listas
 * desplegables (validación de datos) para las columnas de Sí/No.
 */
class Xlsx
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /**
     * Filas de la primera hoja como texto. Las celdas vacías intermedias se
     * rellenan con '' para que cada valor quede en la posición de su columna.
     *
     * @return array<int, array<int, string>>|null null si no es un .xlsx legible
     */
    public static function leer(string $ruta): ?array
    {
        $zip = new ZipArchive;

        if ($zip->open($ruta, ZipArchive::RDONLY) !== true) {
            return null;
        }

        try {
            $hoja = self::rutaPrimeraHoja($zip);
            $xml = $hoja ? self::xml($zip->getFromName($hoja)) : null;

            if (! $xml) {
                return null;
            }

            $compartidos = [];
            if ($sst = self::xml($zip->getFromName('xl/sharedStrings.xml'))) {
                foreach ($sst->si as $si) {
                    $compartidos[] = self::texto($si);
                }
            }

            $filas = [];
            foreach ($xml->sheetData->row ?? [] as $row) {
                $fila = [];

                foreach ($row->c as $c) {
                    $columna = self::indiceColumna((string) $c['r']) ?? count($fila);
                    $valor = match ((string) $c['t']) {
                        's' => $compartidos[(int) $c->v] ?? '',
                        'inlineStr' => self::texto($c->is),
                        default => (string) $c->v,
                    };

                    $fila += array_fill(0, $columna, '');
                    $fila[$columna] = $valor;
                }

                ksort($fila);
                $filas[] = $fila;
            }

            return $filas;
        } finally {
            $zip->close();
        }
    }

    /**
     * Arma un .xlsx y devuelve su contenido binario.
     *
     * @param  array<int, array{nombre: string, filas: array<int, array<int, string>>, anchos?: array<int, int>,
     *     encabezado?: bool, listas?: array<string, array{valores: array<int, string>, ayuda?: string}>}>  $hojas
     *     `listas` va de un rango (ej. "D2:E2001") a sus valores permitidos.
     */
    public static function escribir(array $hojas): string
    {
        $archivos = [
            '[Content_Types].xml' => self::tiposDeContenido(count($hojas)),
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/styles.xml' => self::estilos(),
        ];

        $hojasXml = $relaciones = '';

        foreach (array_values($hojas) as $i => $hoja) {
            $n = $i + 1;
            $hojasXml .= '<sheet name="'.self::e($hoja['nombre']).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $relaciones .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
            $archivos["xl/worksheets/sheet{$n}.xml"] = self::hoja($hoja);
        }

        $estilos = count($hojas) + 1;
        $relaciones .= '<Relationship Id="rId'.$estilos.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $archivos['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="'.self::NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$hojasXml.'</sheets></workbook>';
        $archivos['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relaciones.'</Relationships>';

        $temporal = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($temporal, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($archivos as $nombre => $contenido) {
            $zip->addFromString($nombre, $contenido);
        }

        $zip->close();
        $binario = (string) file_get_contents($temporal);
        @unlink($temporal);

        return $binario;
    }

    /* ---------------- Lectura ---------------- */

    /** La primera hoja según el libro (no siempre se llama sheet1.xml). */
    private static function rutaPrimeraHoja(ZipArchive $zip): ?string
    {
        $libro = self::xml($zip->getFromName('xl/workbook.xml'));
        $rels = self::xml($zip->getFromName('xl/_rels/workbook.xml.rels'));

        if (! $libro || ! $rels || ! isset($libro->sheets->sheet[0])) {
            return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $id = (string) $libro->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];

        foreach ($rels->Relationship as $rel) {
            if ((string) $rel['Id'] === $id) {
                $destino = ltrim((string) $rel['Target'], '/');

                return str_starts_with($destino, 'xl/') ? $destino : 'xl/'.$destino;
            }
        }

        return null;
    }

    private static function xml(string|false $contenido): ?SimpleXMLElement
    {
        if ($contenido === false || $contenido === '') {
            return null;
        }

        $xml = @simplexml_load_string($contenido, SimpleXMLElement::class, LIBXML_NONET);

        return $xml ?: null;
    }

    /** Texto de un <si> o <is>: plano (<t>) o con formato por tramos (<r><t>). */
    private static function texto(?SimpleXMLElement $nodo): string
    {
        if (! $nodo) {
            return '';
        }

        if (isset($nodo->t)) {
            return (string) $nodo->t;
        }

        $texto = '';
        foreach ($nodo->r as $tramo) {
            $texto .= (string) $tramo->t;
        }

        return $texto;
    }

    /** "C12" → 2 (base cero). */
    private static function indiceColumna(string $referencia): ?int
    {
        if (! preg_match('/^([A-Z]+)/', $referencia, $m)) {
            return null;
        }

        $indice = 0;
        foreach (str_split($m[1]) as $letra) {
            $indice = $indice * 26 + (ord($letra) - 64);
        }

        return $indice - 1;
    }

    /* ---------------- Escritura ---------------- */

    private static function hoja(array $hoja): string
    {
        $encabezado = $hoja['encabezado'] ?? false;
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="'.self::NS.'">';

        // Encabezado congelado: sigue visible al bajar por 2.000 filas.
        $xml .= $encabezado
            ? '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            : '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';

        if ($anchos = $hoja['anchos'] ?? []) {
            $xml .= '<cols>';
            foreach (array_values($anchos) as $i => $ancho) {
                $xml .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$ancho.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach (array_values($hoja['filas']) as $i => $fila) {
            $n = $i + 1;
            $estilo = $encabezado && $i === 0 ? ' s="1"' : '';
            $xml .= '<row r="'.$n.'">';

            foreach (array_values($fila) as $j => $valor) {
                if ((string) $valor === '') {
                    continue;
                }
                $xml .= '<c r="'.self::letraColumna($j).$n.'" t="inlineStr"'.$estilo.'><is><t xml:space="preserve">'
                    .self::e((string) $valor).'</t></is></c>';
            }

            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if ($listas = $hoja['listas'] ?? []) {
            $xml .= '<dataValidations count="'.count($listas).'">';
            foreach ($listas as $rango => $lista) {
                $ayuda = isset($lista['ayuda'])
                    ? ' showInputMessage="1" promptTitle="Valores" prompt="'.self::e($lista['ayuda']).'"'
                    : '';
                $xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1"'.$ayuda
                    .' errorTitle="Valor no válido" error="Elige un valor de la lista: '.self::e(implode(' o ', $lista['valores'])).'."'
                    .' sqref="'.$rango.'"><formula1>"'.self::e(implode(',', $lista['valores'])).'"</formula1></dataValidation>';
            }
            $xml .= '</dataValidations>';
        }

        return $xml.'</worksheet>';
    }

    private static function letraColumna(int $indice): string
    {
        $letras = '';
        for ($indice++; $indice > 0; $indice = intdiv($indice - 1, 26)) {
            $letras = chr(65 + ($indice - 1) % 26).$letras;
        }

        return $letras;
    }

    /** Estilo 0: normal. Estilo 1: encabezado en negrita, blanco sobre azul. */
    private static function estilos(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="'.self::NS.'">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1F4E79"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private static function tiposDeContenido(int $hojas): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

        for ($n = 1; $n <= $hojas; $n++) {
            $xml .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return $xml.'</Types>';
    }

    private static function e(string $texto): string
    {
        // Quita caracteres de control que el XML no admite y rompen el archivo.
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texto);

        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
