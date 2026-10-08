<?php

/*
|--------------------------------------------------------------------------
| Ciberamenaza Inminente — Contenido del Escape Room
|--------------------------------------------------------------------------
| Valores iniciales de la experiencia. ContenidoSeeder los copia a la base
| de datos y desde ahí se editan en el dashboard: este archivo solo se usa
| en una instalación nueva y como respaldo de la marca.
*/

return [

    /*
    |----------------------------------------------------------------------
    | Parámetros globales del juego
    |----------------------------------------------------------------------
    */
    // Video de cierre que se muestra en la pantalla del certificado (null lo oculta).
    // Ruta dentro de public/.
    'video_final' => 'escape.mp4',

    'duracion_mision' => 180, // segundos por misión (3 min)
    'intentos_por_mision' => 2, // oportunidades por misión; 0 = ilimitadas

    // Caracteres del fragmento que gana cada participante por misión. Cada
    // jugador tiene fragmentos propios, así que no son intercambiables.
    'largo_fragmento' => 3,

    /*
    |----------------------------------------------------------------------
    | Marca del cliente (multiempresa)
    |----------------------------------------------------------------------
    | Para montar el juego con otra empresa basta con cambiar este bloque y
    | dejar su logo en public/marca/. El logo se muestra siempre sobre una
    | placa blanca, así funciona sin importar sus colores y no hay que
    | retocarlo para que conviva con la paleta oscura del juego.
    |
    | Formato recomendado: SVG con fondo transparente. Sirve cualquier
    | proporción: la placa fija el alto y el ancho se ajusta solo.
    */
    'marca' => [
        'titulo' => 'Ciberamenaza Inminente',
        'subtitulo' => 'Protocolo de contención · 2026',
        'cliente' => 'SOE360',
        'logo' => 'marca/logo-soe.png', // ruta dentro de public/ (null lo oculta)
        'logo_alt' => 'SOE360',
    ],

    /*
    |----------------------------------------------------------------------
    | Misiones (contenido inicial)
    |----------------------------------------------------------------------
    | Cada misión es una estación de la sala con un reto físico. El
    | participante escanea su QR (arranca el cronómetro), resuelve el reto y
    | escribe en el celular el código que entrega la estación.
    |
    | ContenidoSeeder copia esto a la base de datos; desde ahí todo se edita
    | en el dashboard, incluido el 'codigo' de cada estación. Sin código, la
    | misión no se puede superar.
    */
    'misiones' => [

        [
            'slug' => 'phishing',
            'titulo' => 'Bandeja Comprometida',
            'etiqueta' => 'Phishing',
            'icono' => 'mail',
            'duracion' => 180,
            'brief' => 'El servidor de correo fue inundado con mensajes fraudulentos. Alguien está a punto de hacer clic.',
            'instruccion' => 'Analiza los correos de la estación e identifica los <strong>maliciosos</strong>. Al resolver el reto obtendrás el <strong>código de la misión</strong>.',
            'codigo' => null,
        ],

        [
            'slug' => 'contrasenas',
            'titulo' => 'Bóveda de Credenciales',
            'etiqueta' => 'Contraseñas',
            'icono' => 'key',
            'duracion' => 180,
            'brief' => 'El atacante está haciendo fuerza bruta contra el directorio activo. Solo las credenciales robustas resistirán.',
            'instruccion' => 'Audita las credenciales de la estación contra la política de contraseñas. Al resolver el reto obtendrás el <strong>código de la misión</strong>.',
            'codigo' => null,
        ],

        [
            'slug' => 'clasificacion',
            'titulo' => 'Archivo Expuesto',
            'etiqueta' => 'Clasificación',
            'icono' => 'folder',
            'duracion' => 180,
            'brief' => 'Los documentos perdieron sus etiquetas durante el cifrado. Hay que saber qué información quedó expuesta.',
            'instruccion' => 'Lee los documentos de la estación y asígnale a cada uno su nivel de clasificación. Al resolver el reto obtendrás el <strong>código de la misión</strong>.',
            'codigo' => null,
        ],

        [
            'slug' => 'politicas',
            'titulo' => 'Ronda de Inspección',
            'etiqueta' => 'Políticas de Ciberseguridad',
            'icono' => 'camera',
            'duracion' => 240,
            'brief' => 'El atacante no forzó ninguna puerta: aprovechó descuidos en la oficina.',
            'instruccion' => 'Inspecciona las escenas de la estación y dictamina si cada una <strong>cumple</strong> o <strong>incumple</strong> la política. Al resolver el reto obtendrás el <strong>código de la misión</strong>.',
            'codigo' => null,
        ],

        [
            'slug' => 'ia-fuera-de-control',
            'titulo' => 'IA fuera de control',
            'etiqueta' => 'IA',
            'icono' => 'ia',
            'duracion' => 300,
            'intentos' => 0,   // oportunidades ilimitadas
            'brief' => 'El atacante está usando una herramienta de IA para acceder a información de la organización: recibió instrucciones maliciosas para saltarse sus controles.',
            'instruccion' => '<strong>DETENTE, VERIFICA y REPORTA:</strong> identifica qué instrucciones intentan manipular la IA. Al resolver el reto obtendrás el <strong>código de la misión</strong>.',
            'codigo' => null,
        ],

    ],

];
