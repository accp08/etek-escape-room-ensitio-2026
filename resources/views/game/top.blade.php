{{-- Top 3: pantalla pública que se proyecta en la sala. Un podio por cada
     dominio permitido (Parámetros); si hay varios, rotan solos. Los podios
     se refrescan sin recargar la página (ver iniciarTop en app.js).

     Todo se mide con --u (ver .proyeccion en app.css).
     Teclado: F = pantalla completa. --}}
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ txt('top.titulo', 'Top 3') }} — {{ ajuste('juego_titulo', $marca['titulo'] ?? 'Ciberamenaza Inminente') }}</title>
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|jetbrains-mono:400,700,800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="proyeccion top" data-proyeccion data-top="{{ route('top.podios') }}">

    <div class="proy-fondo" aria-hidden="true">
        <span class="proy-orbe proy-orbe-cian"></span>
        <span class="proy-orbe proy-orbe-rosa"></span>
    </div>

    {{-- Destellos que suben detrás del podio --}}
    <div class="top-chispas" aria-hidden="true">
        @for ($i = 0; $i < 24; $i++)
            <span style="--x: {{ ($i * 37) % 100 }}%; --d: {{ 6 + ($i * 7) % 9 }}s; --r: -{{ ($i * 13) % 11 }}s; --s: {{ 0.3 + (($i * 5) % 7) / 10 }}"></span>
        @endfor
    </div>

    <header class="proy-cabecera">
        <div class="proy-marca">
            @include('partials.logo', ['tam' => 'md'])
            <div>
                <p class="proy-juego">{{ ajuste('juego_titulo', $marca['titulo'] ?? 'Ciberamenaza Inminente') }}</p>
                @if ($sub = ajuste('juego_subtitulo'))
                    <p class="proy-juego-sub">{{ $sub }}</p>
                @endif
            </div>
        </div>

        <div class="cons-estado-sistema">
            <span class="cons-punto"></span>
            {{ txt('top.en_vivo', 'En vivo') }}
        </div>
    </header>

    <main class="top-cuerpo">
        <div class="top-podios" data-podios data-firma="{{ $firma }}">
            @include('partials.podios')
        </div>

        @if (count($podios) > 1)
            <div class="top-puntos" data-puntos>
                @foreach ($podios as $podio)
                    <span @if ($loop->first) data-activo @endif></span>
                @endforeach
            </div>
        @endif

        <p class="top-criterio">{{ txt('top.criterio', 'Más misiones superadas primero; a igualdad, menor tiempo.') }}</p>
    </main>

    {{-- Controles: se ocultan solos cuando el mouse no se mueve --}}
    <nav class="proy-controles" data-proy-controles>
        <button type="button" class="proy-boton proy-boton-principal" data-proy-pantalla-completa title="Pantalla completa (F)">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>
            </svg>
            <span data-proy-pantalla-completa-texto>Pantalla completa</span>
        </button>
    </nav>
</body>
</html>
