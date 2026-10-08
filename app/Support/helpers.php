<?php

use App\Support\Contenido;

if (! function_exists('txt')) {
    /** Texto de interfaz editable desde el dashboard. */
    function txt(string $clave, string $porDefecto = ''): string
    {
        return Contenido::texto($clave, $porDefecto);
    }
}

if (! function_exists('ajuste')) {
    /** Parámetro del juego editable desde el dashboard. */
    function ajuste(string $clave, mixed $porDefecto = null): mixed
    {
        return Contenido::ajuste($clave, $porDefecto);
    }
}
