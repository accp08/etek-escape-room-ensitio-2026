{{-- Pantalla de proyección de una estación: se pone en el televisor junto
     al reto físico. Muestra el QR en grande y las instrucciones de la misión.
     Todo se mide con --u (ver .proyeccion en app.css), así se ve igual en un
     portátil que en un televisor 4K.

     Es pública (sin sesión) para dejarla abierta en el televisor.

     Teclado: F = pantalla completa · M = sonido · ← / → = misión anterior / siguiente. --}}
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $mision->titulo }} — Estación</title>
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|jetbrains-mono:400,700,800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="proyeccion" data-proyeccion data-qr-fuente="{{ route('dashboard.misiones.proyectar', $mision) }}"
      @if ($anterior) data-anterior="{{ route('dashboard.misiones.proyectar', $anterior) }}" @endif
      @if ($siguiente) data-siguiente="{{ route('dashboard.misiones.proyectar', $siguiente) }}" @endif>

    <div class="proy-fondo" aria-hidden="true">
        <span class="proy-orbe proy-orbe-cian"></span>
        <span class="proy-orbe proy-orbe-rosa"></span>
    </div>

    {{-- Cabecera: número de estación a la izquierda, marca a la derecha --}}
    <header class="proy-cabecera">
        @if ($numero)
            <div class="proy-estacion">
                <span>{{ txt('proyeccion.estacion', 'Estación') }}</span>
                <strong>{{ str_pad($numero, 2, '0', STR_PAD_LEFT) }}</strong>
                <span class="proy-estacion-total">/ {{ str_pad($total, 2, '0', STR_PAD_LEFT) }}</span>
            </div>
        @endif

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

    <main class="proy-cuerpo">

        {{-- QR --}}
        <section class="proy-qr-zona">
            <div class="proy-qr-marco">
                <span class="proy-esquina ar-iz"></span>
                <span class="proy-esquina ar-de"></span>
                <span class="proy-esquina ab-iz"></span>
                <span class="proy-esquina ab-de"></span>

                <div class="proy-qr-placa">
                    <canvas data-qr="{{ $mision->urlActivacion() }}" data-qr-nivel="Q" width="1024" height="1024"
                            aria-label="Código QR para activar la misión {{ $mision->titulo }}"></canvas>
                    <span class="proy-qr-barrido" aria-hidden="true"></span>
                </div>
            </div>

            <div class="proy-llamado">
                <span class="proy-llamado-icono">
                    @include('partials.icono', ['n' => 'camera', 'c' => 'w-full h-full'])
                </span>
                <div>
                    <p class="proy-llamado-titulo">{{ txt('proyeccion.llamado_titulo', 'Escanea para activar la misión') }}</p>
                    <p class="proy-llamado-texto">{{ txt('proyeccion.llamado_texto', 'Con la app del juego o con la cámara de tu celular') }}</p>
                </div>
            </div>
        </section>

        {{-- Misión --}}
        <section class="proy-info">
            <div class="proy-titulo-fila">
                <span class="proy-icono">
                    @include('partials.icono', ['n' => $mision->icono, 'c' => 'w-full h-full'])
                </span>
                <div class="min-w-0">
                    @if ($mision->etiqueta)
                        <p class="proy-etiqueta">{{ $mision->etiqueta }}</p>
                    @endif
                    <h1 class="proy-titulo">{{ $mision->titulo }}</h1>
                </div>
            </div>

            @if ($mision->brief)
                <p class="proy-brief">{{ $mision->brief }}</p>
            @endif

            @if (trim(strip_tags($mision->instruccion ?? '')) !== '')
                <div class="proy-instruccion">
                    <p class="proy-rotulo">{{ txt('proyeccion.instrucciones', 'Instrucciones de la misión') }}</p>
                    <div class="proy-instruccion-texto">{!! $mision->instruccion !!}</div>
                </div>
            @endif

            <ol class="proy-pasos">
                <li class="proy-paso" style="--i:0">
                    <span class="proy-paso-num">1</span>
                    <span class="proy-paso-icono">@include('partials.icono', ['n' => 'camera', 'c' => 'w-full h-full'])</span>
                    <span class="proy-paso-texto"><strong>{{ txt('proyeccion.paso1_accion', 'Escanea') }}</strong> {{ txt('proyeccion.paso1_texto', 'el código QR') }}</span>
                </li>
                <li class="proy-paso" style="--i:1">
                    <span class="proy-paso-num">2</span>
                    <span class="proy-paso-icono">@include('partials.icono', ['n' => 'search', 'c' => 'w-full h-full'])</span>
                    <span class="proy-paso-texto"><strong>{{ txt('proyeccion.paso2_accion', 'Resuelve') }}</strong> {{ txt('proyeccion.paso2_texto', 'la misión de la estación') }}</span>
                </li>
                <li class="proy-paso" style="--i:2">
                    <span class="proy-paso-num">3</span>
                    <span class="proy-paso-icono">@include('partials.icono', ['n' => 'lock', 'c' => 'w-full h-full'])</span>
                    <span class="proy-paso-texto"><strong>{{ txt('proyeccion.paso3_accion', 'Ingresa') }}</strong> {{ txt('proyeccion.paso3_texto', 'el número identificador de la ficha en tu celular') }}</span>
                </li>
            </ol>

            <div class="proy-datos">
                <div class="proy-dato">
                    @include('partials.icono', ['n' => 'clock', 'c' => 'proy-dato-icono'])
                    <div>
                        <p class="proy-dato-valor">{{ sprintf('%02d:%02d', intdiv($mision->duracion, 60), $mision->duracion % 60) }}</p>
                        <p class="proy-dato-etiqueta">{{ txt('proyeccion.dato_tiempo', 'Tiempo · inicia al escanear') }}</p>
                    </div>
                </div>
                <div class="proy-dato">
                    @include('partials.icono', ['n' => 'shield', 'c' => 'proy-dato-icono'])
                    <div>
                        <p class="proy-dato-valor">{{ $mision->intentosLegible() }}</p>
                        <p class="proy-dato-etiqueta">{{ $mision->intentosIlimitados() ? txt('proyeccion.dato_ilimitadas', 'Oportunidades ilimitadas') : txt('proyeccion.dato_intentos', 'Oportunidades') }}</p>
                    </div>
                </div>
                <div class="proy-dato">
                    @include('partials.icono', ['n' => 'key', 'c' => 'proy-dato-icono'])
                    <div>
                        <p class="proy-dato-valor">{{ txt('proyeccion.dato_fragmento_valor', '1') }}</p>
                        <p class="proy-dato-etiqueta">{{ txt('proyeccion.dato_fragmento', 'Fragmento del código') }}</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @if ($audio = $mision->audioUrl())
        <audio src="{{ $audio }}" loop preload="auto" data-proy-audio></audio>
    @endif

    {{-- Controles: se ocultan solos cuando el mouse no se mueve --}}
    <nav class="proy-controles" data-proy-controles>
        @if ($anterior)
            <a href="{{ route('dashboard.misiones.proyectar', $anterior) }}" class="proy-boton" title="Misión anterior (←)">
                @include('partials.icono', ['n' => 'back', 'c' => 'w-4 h-4'])
            </a>
        @endif

        <button type="button" class="proy-boton proy-boton-principal" data-proy-pantalla-completa title="Pantalla completa (F)">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>
            </svg>
            <span data-proy-pantalla-completa-texto>Pantalla completa</span>
        </button>

        @includeWhen($audio, 'partials.boton-sonido')

        @if ($siguiente)
            <a href="{{ route('dashboard.misiones.proyectar', $siguiente) }}" class="proy-boton" title="Misión siguiente (→)">
                @include('partials.icono', ['n' => 'back', 'c' => 'w-4 h-4 rotate-180'])
            </a>
        @endif

        @auth
            <a href="{{ route('dashboard.misiones.editar', $mision) }}" class="proy-boton" title="Volver al dashboard">
                @include('partials.icono', ['n' => 'x', 'c' => 'w-4 h-4'])
            </a>
        @endauth
    </nav>

    @auth
        @unless ($mision->tieneCodigo())
            {{-- Solo para el administrador: desaparece junto con los controles. --}}
            <p class="proy-aviso" data-proy-controles>Esta misión aún no tiene código: nadie podrá superarla.</p>
        @endunless
    @endauth
</body>
</html>
