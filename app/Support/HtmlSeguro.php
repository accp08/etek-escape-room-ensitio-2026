<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanea el HTML que el administrador escribe en el dashboard.
 *
 * Ese contenido se imprime en el juego con {!! !!}, así que no puede pasar tal
 * cual: se recorre el árbol y se conserva únicamente una lista blanca de
 * etiquetas y atributos. Todo lo demás se descarta conservando su texto.
 */
class HtmlSeguro
{
    /** Etiquetas permitidas y, por cada una, sus atributos permitidos. */
    private const PERMITIDAS = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'code' => [],
        'small' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'a' => ['href'],
    ];

    /**
     * Las imágenes solo se admiten en el contenido de los ítems (cuerpo de los
     * correos y de los documentos), nunca en la instrucción de la misión.
     * Estos son los valores admitidos para su tamaño y su posición.
     */
    private const ANCHOS = ['25', '50', '75', '100'];

    private const ALINEACIONES = ['izquierda', 'centro', 'derecha'];

    /**
     * Solo se aceptan imágenes subidas desde el dashboard. Se guardan con la
     * ruta relativa a public/ para que no dependan del dominio ni de la
     * subcarpeta en que esté publicada la aplicación (ver conRutas()).
     */
    private const RUTA_IMAGEN = '#^(?:https?://[^/]+)?(?:/[^?\#]*?)?/?(storage/misiones/[a-z0-9_-]+/contenido/[A-Za-z0-9_-]+\.(?:png|jpe?g|webp|gif))$#i';

    /** Esquemas admitidos en los enlaces. */
    private const ESQUEMAS = ['http', 'https', 'mailto', 'tel'];

    /**
     * Etiquetas que se eliminan junto con su contenido. En el resto basta con
     * quitar la etiqueta y conservar el texto, pero el contenido de estas no es
     * texto que el participante deba leer.
     */
    private const VACIAR = [
        'script', 'style', 'iframe', 'object', 'embed',
        'noscript', 'svg', 'math', 'template', 'form', 'input',
    ];

    public static function limpiar(?string $html, bool $imagenes = false): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument;

        // El envoltorio evita que DOMDocument añada <html><body> al resultado.
        $previo = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="raiz">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        $raiz = $doc->getElementById('raiz');

        if (! $raiz) {
            return '';
        }

        self::podar($raiz, $imagenes);

        $salida = '';
        foreach ($raiz->childNodes as $hijo) {
            $salida .= $doc->saveHTML($hijo);
        }

        return trim($salida);
    }

    /**
     * Convierte el HTML del editor en párrafos, que es como los guarda el juego.
     *
     * @return array<int, string> el contenido interno de cada párrafo
     */
    public static function parrafos(?string $html, bool $imagenes = false): array
    {
        $limpio = self::limpiar($html, $imagenes);

        if ($limpio === '') {
            return [];
        }

        $doc = new DOMDocument;
        $previo = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="raiz">'.$limpio.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        $raiz = $doc->getElementById('raiz');
        $parrafos = [];
        $sueltos = '';

        foreach ($raiz?->childNodes ?? [] as $hijo) {
            if ($hijo instanceof DOMElement && $hijo->nodeName === 'p') {
                $interno = '';
                foreach ($hijo->childNodes as $nieto) {
                    $interno .= $doc->saveHTML($nieto);
                }
                $interno = trim($interno);

                if ($interno !== '' && $interno !== '<br>') {
                    $parrafos[] = $interno;
                }

                continue;
            }

            // Contenido fuera de <p>: se acumula como un párrafo más.
            $sueltos .= $doc->saveHTML($hijo);
        }

        if (trim(strip_tags($sueltos, '<br>')) !== '' || trim($sueltos) !== '') {
            $sueltos = trim($sueltos);
            if ($sueltos !== '' && (strip_tags($sueltos) !== '' || str_contains($sueltos, '<img'))) {
                $parrafos[] = $sueltos;
            }
        }

        return $parrafos;
    }

    /**
     * Antepone la URL pública a las imágenes guardadas con ruta relativa.
     * Se usa al imprimir el contenido, tanto en el juego como en el editor.
     */
    public static function conRutas(?string $html): string
    {
        return str_replace('src="storage/', 'src="'.asset('storage').'/', (string) $html);
    }

    /** Recorre el árbol quitando lo que no está en la lista blanca. */
    private static function podar(DOMNode $nodo, bool $imagenes = false): void
    {
        foreach (iterator_to_array($nodo->childNodes) as $hijo) {

            if ($hijo instanceof DOMElement) {
                $etiqueta = strtolower($hijo->nodeName);

                if ($etiqueta === 'img') {
                    $imagenes ? self::sanearImagen($hijo) : $nodo->removeChild($hijo);

                    continue;
                }

                if (in_array($etiqueta, self::VACIAR, true)) {
                    $nodo->removeChild($hijo);

                    continue;
                }

                if (! array_key_exists($etiqueta, self::PERMITIDAS)) {
                    // Se conserva el texto, se descarta la etiqueta.
                    self::podar($hijo, $imagenes);
                    self::desenvolver($hijo);

                    continue;
                }

                foreach (iterator_to_array($hijo->attributes ?? []) as $atributo) {
                    $nombre = strtolower($atributo->nodeName);

                    if (! in_array($nombre, self::PERMITIDAS[$etiqueta], true)) {
                        $hijo->removeAttribute($atributo->nodeName);

                        continue;
                    }

                    if ($nombre === 'href' && ! self::enlaceValido($atributo->nodeValue)) {
                        $hijo->removeAttribute('href');
                    }
                }

                self::podar($hijo, $imagenes);

                continue;
            }

            // Comentarios, CDATA y demás: fuera.
            if (! $hijo instanceof \DOMText) {
                $nodo->removeChild($hijo);
            }
        }
    }

    /** Deja solo una imagen propia con tamaño y alineación válidos, o la elimina. */
    private static function sanearImagen(DOMElement $img): void
    {
        if (! preg_match(self::RUTA_IMAGEN, trim($img->getAttribute('src')), $coincidencia)) {
            $img->parentNode->removeChild($img);

            return;
        }

        $alt = trim($img->getAttribute('alt'));
        $ancho = $img->getAttribute('data-ancho');
        $alinear = $img->getAttribute('data-alinear');

        foreach (iterator_to_array($img->attributes) as $atributo) {
            $img->removeAttribute($atributo->nodeName);
        }

        $img->setAttribute('src', $coincidencia[1]);
        $img->setAttribute('alt', mb_substr($alt, 0, 160));
        $img->setAttribute('data-ancho', in_array($ancho, self::ANCHOS, true) ? $ancho : '100');
        $img->setAttribute('data-alinear', in_array($alinear, self::ALINEACIONES, true) ? $alinear : 'centro');
    }

    /** Sustituye un elemento por sus hijos. */
    private static function desenvolver(DOMElement $elemento): void
    {
        $padre = $elemento->parentNode;

        while ($elemento->firstChild) {
            $padre->insertBefore($elemento->firstChild, $elemento);
        }

        $padre->removeChild($elemento);
    }

    private static function enlaceValido(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }

        $esquema = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($esquema, self::ESQUEMAS, true);
    }
}
