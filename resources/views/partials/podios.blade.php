{{-- Podios de la pantalla /top: uno por dominio permitido. Lo pinta
     TopController y la pantalla lo vuelve a pedir cada cierto tiempo. --}}
@php
    $iniciales = fn (string $nombre) => \Illuminate\Support\Str::of($nombre)->explode(' ')->filter()->take(2)
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $medallas = [1 => 'oro', 2 => 'plata', 3 => 'bronce'];
@endphp

@foreach ($podios as $podio)
    <section class="top-slide" data-slide @if ($loop->first) data-activo @endif>
        <div class="top-encabezado">
            <p class="proy-rotulo">{{ txt('top.rotulo', 'Los mejores agentes') }}</p>
            <h1 class="top-titulo">{{ txt('top.titulo', 'Top 3') }}</h1>
            <p class="top-dominio">
                @if ($podio['dominio'])
                    <span class="top-arroba">@</span>{{ $podio['dominio'] }}
                @else
                    {{ txt('top.general', 'Ranking general') }}
                @endif
            </p>
        </div>

        <div class="top-podio">
            {{-- Orden visual: 2.º, 1.º, 3.º --}}
            @foreach ([2, 1, 3] as $puesto)
                @php $fila = $podio['puestos'][$puesto - 1]; @endphp
                <div class="top-lugar top-{{ $medallas[$puesto] }} {{ $fila ? '' : 'top-vacio' }}" style="--i: {{ [3 => 0, 2 => 1, 1 => 2][$puesto] }}">
                    <div class="top-jugador">
                        @if ($puesto === 1)
                            <svg class="top-corona" viewBox="0 0 64 40" aria-hidden="true">
                                <path d="M4 34 8 8l14 14L32 2l10 20L56 8l4 26Z" fill="currentColor"/>
                                <rect x="4" y="34" width="56" height="5" rx="2" fill="currentColor"/>
                            </svg>
                        @endif

                        <div class="top-avatar">
                            <span>{{ $fila ? $iniciales($fila['nombre']) : '?' }}</span>
                        </div>

                        @if ($fila)
                            <p class="top-nombre">{{ $fila['nombre'] }}</p>
                            @if ($fila['area'])
                                <p class="top-area">{{ $fila['area'] }}</p>
                            @endif
                            <div class="top-datos">
                                <span>@include('partials.icono', ['n' => 'clock', 'c' => 'top-dato-icono']){{ $fila['tiempo'] }}</span>
                                <span>@include('partials.icono', ['n' => 'shield', 'c' => 'top-dato-icono']){{ $fila['aciertos'] }}/{{ $fila['total'] }}</span>
                            </div>
                        @else
                            <p class="top-nombre">{{ txt('top.vacio', 'Por definir') }}</p>
                            <p class="top-area">{{ txt('top.vacio_texto', '¿Serás tú?') }}</p>
                        @endif
                    </div>

                    <div class="top-pedestal">
                        <span class="top-numero">{{ $puesto }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endforeach
