{{-- Consola de detención: pantalla pública que se proyecta en la sala.
     Izquierda: el jugador digita con el teclado el código que ve en su
     celular. Derecha: el top 10 en vivo. El código identifica al jugador, así
     que no hay que iniciar sesión.

     Todo se mide con --u (ver .proyeccion en app.css). La lógica vive en
     resources/js/consola.js. --}}
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ txt('consola.titulo', 'Consola de detención') }}</title>
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|jetbrains-mono:400,700,800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="proyeccion consola" data-proyeccion data-consola
      data-verificar="{{ route('consola.verificar') }}"
      data-ranking="{{ route('consola.ranking') }}">

    <div class="proy-fondo" aria-hidden="true">
        <span class="proy-orbe proy-orbe-cian"></span>
        <span class="proy-orbe proy-orbe-rosa"></span>
    </div>

    {{-- Destello de alarma (código incorrecto) --}}
    <div class="cons-alarma" aria-hidden="true"></div>

    {{-- Confeti (código correcto) --}}
    <canvas class="cons-confeti" data-confeti aria-hidden="true"></canvas>

    {{-- Cabecera: estado del sistema a la izquierda, marca a la derecha --}}
    <header class="proy-cabecera">
        <div class="cons-estado-sistema">
            <span class="cons-punto"></span>
            {{ txt('consola.estado_sistema', 'Sistemas cifrados · esperando código') }}
        </div>

        <div class="proy-marca">
            <div>
                <p class="proy-juego">{{ ajuste('juego_titulo', $marca['titulo'] ?? 'Ciberamenaza Inminente') }}</p>
                @if ($sub = ajuste('juego_subtitulo'))
                    <p class="proy-juego-sub">{{ $sub }}</p>
                @endif
            </div>
            @include('partials.logo', ['tam' => 'md'])
        </div>
    </header>

    <main class="cons-cuerpo">

        {{-- Terminal: digitar el código --}}
        <section class="cons-terminal" data-terminal data-estado="espera">
            <div class="cons-ventana">
                <div class="cons-ventana-barra">
                    <span></span><span></span><span></span>
                    <p>{{ txt('consola.terminal', 'recovery@core — /decrypt') }}</p>
                </div>

                <div class="cons-ventana-cuerpo">
                    {{-- Espera: digitar --}}
                    <div class="cons-vista cons-vista-espera">
                        <p class="proy-rotulo">{{ txt('consola.titulo', 'Consola de detención') }}</p>
                        <h1 class="cons-instruccion">{{ txt('consola.instruccion', 'Digita el código de detención que aparece en tu celular') }}</h1>

                        <label class="cons-casillas" data-casillas>
                            <input type="text" class="cons-entrada" data-entrada
                                   maxlength="{{ $misiones * $largo }}" autocomplete="off" autocapitalize="characters"
                                   autocorrect="off" spellcheck="false" autofocus aria-label="Código de detención">

                            @for ($m = 0; $m < $misiones; $m++)
                                <span class="cons-grupo">
                                    @for ($c = 0; $c < $largo; $c++)
                                        <span class="cons-casilla" data-casilla></span>
                                    @endfor
                                </span>
                            @endfor
                        </label>

                        <p class="cons-aviso" data-aviso></p>

                        <div class="cons-ayuda">
                            <span><kbd>A–Z</kbd><kbd>0–9</kbd> {{ txt('consola.ayuda_escribir', 'escribe') }}</span>
                            <span><kbd>⌫</kbd> {{ txt('consola.ayuda_borrar', 'borra') }}</span>
                            <span><kbd>Enter</kbd> {{ txt('consola.ayuda_ejecutar', 'ejecuta') }}</span>
                        </div>

                        <button type="button" class="cons-boton" data-enviar>
                            @include('partials.icono', ['n' => 'lock', 'c' => 'w-[1.2em] h-[1.2em]'])
                            {{ txt('consola.boton', 'Ejecutar detención') }}
                        </button>
                    </div>

                    {{-- Verificando --}}
                    <div class="cons-vista cons-vista-verificando">
                        <div class="cons-giro"></div>
                        <p class="cons-verificando-texto">{{ txt('consola.verificando', 'Descifrando sistemas') }}<span class="cons-puntos"></span></p>
                        <div class="cons-progreso"><span></span></div>
                        <p class="cons-log" data-log></p>
                    </div>

                    {{-- Éxito --}}
                    <div class="cons-vista cons-vista-exito">
                        <div class="cons-escudo">
                            <span class="cons-onda"></span><span class="cons-onda"></span><span class="cons-onda"></span>
                            @include('partials.icono', ['n' => 'shield', 'c' => 'w-full h-full'])
                        </div>
                        <p class="cons-exito-titulo">{{ txt('consola.exito_titulo', 'Amenaza contenida') }}</p>
                        <p class="cons-exito-nombre" data-saludo></p>

                        <div class="cons-exito-datos">
                            <div><p data-tiempo></p><span>{{ txt('consola.dato_tiempo', 'Tiempo') }}</span></div>
                            <div><p data-puesto></p><span>{{ txt('consola.dato_puesto', 'Puesto') }}</span></div>
                        </div>

                        <p class="cons-exito-mensaje" data-mensaje></p>
                    </div>

                    {{-- Error --}}
                    <div class="cons-vista cons-vista-error">
                        <div class="cons-error-icono">
                            @include('partials.icono', ['n' => 'x', 'c' => 'w-full h-full'])
                        </div>
                        <p class="cons-error-titulo" data-texto-glitch="{{ txt('consola.error_titulo', 'Acceso denegado') }}">{{ txt('consola.error_titulo', 'Acceso denegado') }}</p>
                        <p class="cons-error-texto" data-error-texto>{{ txt('consola.error_texto', 'El código no es válido. Revisa tu celular e inténtalo de nuevo.') }}</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Top 10 --}}
        <aside class="cons-ranking">
            <div class="cons-ranking-cabecera">
                <span class="cons-trofeo">@include('partials.icono', ['n' => 'trophy', 'c' => 'w-full h-full'])</span>
                <div>
                    <p class="proy-rotulo">{{ txt('consola.top_rotulo', 'Ranking en vivo') }}</p>
                    <h2 class="cons-ranking-titulo">{{ txt('consola.top_titulo', 'Top 10') }}</h2>
                </div>
            </div>

            <ol class="cons-lista" data-lista>
                @forelse ($ranking as $fila)
                    <li class="cons-fila" data-partida="{{ $fila['partida'] }}" style="--i:{{ $loop->index }}">
                        <span class="cons-fila-puesto">{{ $fila['puesto'] }}</span>
                        <span class="cons-fila-nombre">
                            <strong>{{ $fila['nombre'] }}</strong>
                            @if ($fila['area'])<small>{{ $fila['area'] }}</small>@endif
                        </span>
                        <span class="cons-fila-aciertos">{{ $fila['aciertos'] }}/{{ $fila['total'] }}</span>
                        <span class="cons-fila-tiempo">{{ $fila['tiempo'] }}</span>
                    </li>
                @empty
                    <li class="cons-vacio">{{ txt('consola.top_vacio', 'Aún nadie ha recuperado los sistemas. ¡Sé el primero!') }}</li>
                @endforelse
            </ol>

            <p class="cons-criterio">{{ txt('consola.top_criterio', 'Más misiones superadas primero; a igualdad, menor tiempo.') }}</p>
        </aside>
    </main>

    {{-- Textos que pinta consola.js (editables en Dashboard → Textos → Cierre) --}}
    @php
        $textosJs = [
            'faltan' => txt('consola.faltan', 'Faltan {faltan} caracteres.'),
            'vacio' => txt('consola.vacio', 'Escribe tu código.'),
            'log' => txt('consola.log', "verificando firma…\nensamblando fragmentos…\nabriendo volúmenes cifrados…"),
            'saludo' => txt('consola.exito_saludo', '¡Bien hecho, {nombre}!'),
            'top' => txt('consola.exito_top', '¡Entraste al TOP 10 en el puesto #{puesto}!'),
            'puesto' => txt('consola.exito_puesto', 'Vas en el puesto #{puesto} de {total}.'),
            'fuera' => txt('consola.fuera_ranking', 'Tu partida no entra en el ranking.'),
            'repetido' => txt('consola.exito_repetido', 'Tu código ya estaba registrado. Vas en el puesto #{puesto} de {total}.'),
            'error' => txt('consola.error_texto', 'El código no es válido. Revisa tu celular e inténtalo de nuevo.'),
            'limite' => txt('consola.limite', 'Demasiados intentos seguidos. Espera un minuto e inténtalo de nuevo.'),
            'sin_conexion' => txt('consola.sin_conexion', 'No hay conexión con el servidor. Inténtalo de nuevo.'),
            'top_vacio' => txt('consola.top_vacio', 'Aún nadie ha recuperado los sistemas. ¡Sé el primero!'),
        ];
    @endphp
    <script type="application/json" data-textos-consola>@json($textosJs)</script>

    @if ($audio)
        <audio src="{{ $audio }}" loop preload="auto" data-proy-audio></audio>
    @endif

    @if (ajuste('video_final'))
        {{-- Video de cierre a pantalla completa. El archivo (~10 MB) se pide al abrirlo. --}}
        <div class="cons-video" data-cons-video hidden>
            <video class="cons-video-reproductor" data-video controls playsinline preload="none"
                   data-fuente="{{ route('consola.video') }}">
                Tu navegador no puede reproducir este video.
            </video>
            <button type="button" class="proy-boton cons-video-cerrar" data-cerrar-video title="Cerrar (Esc)">
                @include('partials.icono', ['n' => 'x', 'c' => 'w-4 h-4'])
                Cerrar video
            </button>
        </div>
    @endif

    {{-- Controles: se ocultan solos cuando el mouse no se mueve --}}
    <nav class="proy-controles" data-proy-controles>
        @includeWhen($audio, 'partials.boton-sonido')

        @if (ajuste('video_final'))
            <button type="button" class="proy-boton" data-abrir-video title="Ver el video de la experiencia">
                @include('partials.icono', ['n' => 'play', 'c' => 'w-4 h-4'])
                Ver video
            </button>
        @endif

        <button type="button" class="proy-boton proy-boton-principal" data-proy-pantalla-completa title="Pantalla completa">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>
            </svg>
            <span data-proy-pantalla-completa-texto>Pantalla completa</span>
        </button>
    </nav>
</body>
</html>
