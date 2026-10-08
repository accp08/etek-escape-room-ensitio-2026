<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('titulo', 'Dashboard') · {{ ajuste('juego_titulo', 'Ciberamenaza Inminente') }}</title>

    <meta name="theme-color" content="#0b1220">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}" type="image/png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|jetbrains-mono:400,700,800" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

@guest
    <div class="panel-admin">
        <div class="admin-angosto">@yield('contenido')</div>
    </div>
@endguest

@auth
    @php
        $menu = [
            'General' => [
                ['dashboard',               'Dashboard',      'trophy',  null],
            ],
            'Contenido' => [
                ['dashboard.misiones',      'Misiones',       'folder',  \App\Models\Mision::count()],
                ['dashboard.briefing',      'Instrucciones',  'play',    \App\Models\PasoBriefing::count()],
                ['dashboard.textos',        'Textos',         'mail',    \App\Models\Texto::count()],
            ],
            'Usuarios' => [
                ['dashboard.participantes', 'Participantes',  'user',    \App\Models\Participante::count()],
            ],
            'Reportes' => [
                ['dashboard.resultados',    'Resultados',     'search',  \App\Models\Partida::count()],
                ['dashboard.registros',     'Bitácora',       'clock',   \App\Models\Registro::count()],
            ],
            'Configuración' => [
                ['dashboard.empresa',       'Empresa',        'shield',  null],
                ['dashboard.ajustes',       'Parámetros',     'lock',    null],
            ],
        ];
    @endphp

    <div class="admin panel-admin !p-0">

        {{-- Barra lateral --}}
        <aside class="lateral" data-lateral>
            <div class="lateral-marca">
                <div class="shrink-0">@include('partials.logo', ['tam' => 'sm'])</div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[12.5px] font-black text-ink">{{ $marca['nombre'] }}</p>
                    <p class="truncate font-mono text-[9.5px] text-muted">Juego en sitio</p>
                </div>

                <button type="button" data-lateral-toggle
                        class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-[rgba(46,91,140,.5)] text-muted lg:hidden"
                        aria-label="Abrir o cerrar el menú">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>

            <div class="lateral-menu">
                @foreach ($menu as $grupo => $enlaces)
                    <p class="lateral-grupo">{{ $grupo }}</p>

                    @foreach ($enlaces as [$ruta, $etiqueta, $icono, $conteo])
                        @php
                            // 'dashboard' es el prefijo de todas las rutas del panel, así que
                            // para esa entrada solo vale la coincidencia exacta.
                            $activa = request()->routeIs($ruta)
                                || ($ruta !== 'dashboard' && request()->routeIs($ruta . '.*'));
                        @endphp
                        <a href="{{ route($ruta) }}" class="lateral-enlace"
                           @if ($activa) aria-current="page" @endif>
                            @include('partials.icono', ['n' => $icono, 'c' => 'w-4 h-4 shrink-0'])
                            <span class="truncate">{{ $etiqueta }}</span>
                            @isset($conteo)<span class="conteo">{{ $conteo }}</span>@endisset
                        </a>
                    @endforeach
                @endforeach

                <div class="mt-6 border-t border-[rgba(46,91,140,.3)] pt-4">
                    <a href="{{ route('login') }}" target="_blank" rel="noopener" class="lateral-enlace">
                        @include('partials.icono', ['n' => 'play', 'c' => 'w-4 h-4 shrink-0'])
                        <span>Abrir el juego</span>
                    </a>

                    <form method="POST" action="{{ route('dashboard.salir') }}">
                        @csrf
                        <button type="submit" class="lateral-enlace w-full">
                            @include('partials.icono', ['n' => 'back', 'c' => 'w-4 h-4 shrink-0'])
                            <span>Cerrar sesión</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Contenido --}}
        <div class="admin-cuerpo">
            <div class="admin-encabezado">
                <div class="min-w-0 flex-1">
                    <p class="eyebrow">@yield('seccion', 'Panel')</p>
                    <h1 class="mt-1 text-[24px] font-black leading-tight text-ink">@yield('titulo', 'Dashboard')</h1>
                </div>

                @hasSection('acciones')
                    <div class="flex flex-wrap items-center gap-2">@yield('acciones')</div>
                @endif
            </div>

            @if (session('exito'))
                <div class="aviso aviso-ok mb-4">
                    @include('partials.icono', ['n' => 'check', 'c' => 'w-4 h-4 shrink-0 mt-px'])
                    <span>{{ session('exito') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="aviso aviso-error mb-4">
                    @include('partials.icono', ['n' => 'alert', 'c' => 'w-4 h-4 shrink-0 mt-px'])
                    <div>@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
                </div>
            @endif

            @yield('contenido')
        </div>
    </div>
@endauth

</body>
</html>
