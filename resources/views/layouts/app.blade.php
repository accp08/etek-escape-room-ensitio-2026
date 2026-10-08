<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <title>@yield('titulo', 'Ciberamenaza Inminente')</title>

    {{-- PWA --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#0b1220">
    <meta name="description" content="Escape room de ciberseguridad: resuelve las misiones y evita que el ciberataque se materialice.">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ciberamenaza">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-180.png') }}">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}" type="image/png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|jetbrains-mono:400,700,800" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="stage">
        <div class="device">
            <div class="device-notch"></div>

            @hasSection('hud')
                @yield('hud')
            @endif

            <div class="device-scroll">
                @yield('contenido')
            </div>

            @hasSection('pie')
                @yield('pie')
            @endif

            {{-- Capa de hojas a pantalla completa (lectores, visores).
                 Va aquí, como hijo directo del dispositivo, para cubrir también
                 la cabecera y el pie de la misión sin salirse del marco. --}}
            @hasSection('hojas')
                @yield('hojas')
            @endif

            {{-- Instrucciones de la misión en curso --}}
            @hasSection('hud')
                @isset($mision)
                    @include('partials.hojas-mision')
                @endisset
            @endif
        </div>
    </div>
</body>
</html>
