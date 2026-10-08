@extends('layouts.dashboard')
@section('seccion', 'General')
@section('titulo', 'Dashboard')

@section('acciones')
    <a href="{{ route('dashboard.resultados.csv') }}" class="btn btn-ghost !w-auto !px-4 !py-2 !text-[12px]">
        @include('partials.icono', ['n' => 'download', 'c' => 'w-3.5 h-3.5'])
        Exportar ranking
    </a>
@endsection

@php
    // Paleta de gráficos: tonos de la marca ajustados para que pasen las
    // comprobaciones de contraste y daltonismo sobre el fondo oscuro.
    $serie = ['#009fb8', '#6a5cff', '#ff2da1', '#17a55c'];

    $maxDia = max(1, $porDia->max('cantidad'));
@endphp

@section('contenido')

{{-- Cifras de cabecera --}}
<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([
        ['Participantes', $resumen['participantes'], $resumen['activos'] . ' activos', $serie[0]],
        ['Partidas',      $resumen['partidas'],      $resumen['completadas'] . ' completadas', $serie[1]],
        ['Tasa de éxito', $resumen['tasa_exito'] . '%', 'Partidas terminadas', $serie[3]],
        ['Precisión',     $resumen['precision'] . '%', 'Aciertos sobre el total', $serie[2]],
    ] as [$etiqueta, $valor, $nota, $tono])
        <div class="kpi" style="--tono: {{ $tono }}">
            <p class="kpi-valor">{{ $valor }}</p>
            <p class="kpi-etiqueta">{{ $etiqueta }}</p>
            <p class="kpi-nota">{{ $nota }}</p>
        </div>
    @endforeach
</div>

{{-- Jugadores en línea --}}
<section class="panel mt-4 p-5" data-en-linea="{{ route('dashboard.en-linea') }}" aria-live="polite">
    @include('dashboard.en-linea')
</section>

<div class="mt-4 grid items-start gap-4 xl:grid-cols-[1.4fr_1fr]">

    {{-- Partidas por día --}}
    <section class="panel p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-[15px] font-black text-ink">Partidas iniciadas por día</h2>
            <span class="eyebrow">Últimos {{ $porDia->count() }} días</span>
        </div>

        @if ($porDia->sum('cantidad') === 0)
            <p class="mt-6 text-center text-[12.5px] text-muted">Todavía no hay partidas registradas.</p>
        @else
            <div class="mt-5 overflow-x-auto">
                <svg viewBox="0 0 {{ $porDia->count() * 44 }} 180" class="h-[180px] w-full min-w-[520px]" role="img"
                     aria-label="Partidas iniciadas por día durante los últimos {{ $porDia->count() }} días">

                    {{-- Guías horizontales --}}
                    @foreach ([0, 0.5, 1] as $g)
                        <line class="grafico-guia" x1="0" x2="{{ $porDia->count() * 44 }}"
                              y1="{{ 140 - 120 * $g }}" y2="{{ 140 - 120 * $g }}"></line>
                        <text class="grafico-eje" x="2" y="{{ 136 - 120 * $g }}">{{ round($maxDia * $g) }}</text>
                    @endforeach

                    @foreach ($porDia as $i => $d)
                        @php
                            $alto = $d['cantidad'] > 0 ? max(4, 120 * $d['cantidad'] / $maxDia) : 0;
                            $x    = $i * 44 + 12;
                        @endphp

                        @if ($alto > 0)
                            {{-- rx redondea el extremo del dato; la base queda anclada --}}
                            <rect class="grafico-barra" style="--serie: {{ $serie[0] }}"
                                  x="{{ $x }}" y="{{ 140 - $alto }}" width="20" height="{{ $alto }}" rx="4">
                                <title>{{ $d['etiqueta'] }} · {{ $d['cantidad'] }} partidas</title>
                            </rect>
                            <text class="grafico-valor" x="{{ $x + 10 }}" y="{{ 134 - $alto }}" text-anchor="middle">{{ $d['cantidad'] }}</text>
                        @else
                            <rect fill="rgba(46,91,140,.28)" x="{{ $x }}" y="138" width="20" height="2" rx="1">
                                <title>{{ $d['etiqueta'] }} · sin partidas</title>
                            </rect>
                        @endif

                        <text class="grafico-eje" x="{{ $x + 10 }}" y="158" text-anchor="middle">{{ $d['dia'] }}</text>
                        <text class="grafico-eje" x="{{ $x + 10 }}" y="170" text-anchor="middle" opacity="0.6">{{ $d['fecha']->format('d') }}</text>
                    @endforeach
                </svg>
            </div>
        @endif
    </section>

    {{-- Bitácora reciente --}}
    <section class="panel p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-[15px] font-black text-ink">Actividad reciente</h2>
            <a href="{{ route('dashboard.registros') }}" class="text-[11px] font-semibold text-muted hover:text-cyan">Ver toda</a>
        </div>

        <div class="mt-3 flex gap-2">
            <span class="badge">{{ $pulso['info'] }} info</span>
            <span class="badge badge-muted">{{ $pulso['aviso'] }} avisos</span>
            <span class="badge badge-pink">{{ $pulso['alerta'] }} alertas</span>
        </div>

        <div class="mt-3">
            @forelse ($actividad as $r)
                <div class="registro">
                    <span class="registro-punto" data-nivel="{{ $r->nivel }}"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[12px] leading-snug text-ink-soft">{{ $r->descripcion }}</p>
                        <p class="mt-0.5 font-mono text-[9.5px] text-muted">
                            {{ $r->autor() }} · {{ $r->canal }} · {{ $r->created_at->diffForHumans(short: true) }}
                        </p>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-[12.5px] text-muted">Sin actividad todavía.</p>
            @endforelse
        </div>
    </section>
</div>

<div class="mt-4 grid items-start gap-4 xl:grid-cols-2">

    {{-- Desempeño por misión --}}
    <section class="panel p-5">
        <h2 class="text-[15px] font-black text-ink">Precisión media por misión</h2>
        <p class="mt-1 text-[11.5px] text-muted">Porcentaje de respuestas correctas sobre todos los intentos.</p>

        @if ($porMision->sum('intentos') === 0)
            <p class="mt-6 text-center text-[12.5px] text-muted">Aún nadie ha jugado estas misiones.</p>
        @else
            <div class="mt-5 space-y-4">
                @foreach ($porMision as $i => $m)
                    <div>
                        <div class="mb-1.5 flex items-baseline justify-between gap-3">
                            <span class="min-w-0 truncate text-[12.5px] font-bold text-ink-soft">{{ $m['mision']->titulo }}</span>
                            <span class="shrink-0 font-mono text-[12px] font-bold" style="color: {{ $serie[$i % 4] }}">{{ $m['precision'] }}%</span>
                        </div>

                        <div class="h-2.5 overflow-hidden rounded-full bg-[rgba(5,12,26,.8)]">
                            <div class="h-full rounded-full transition-all"
                                 style="width: {{ $m['precision'] }}%; background: {{ $serie[$i % 4] }}"
                                 title="{{ $m['mision']->titulo }}: {{ $m['precision'] }}% de precisión"></div>
                        </div>

                        <p class="mt-1.5 font-mono text-[10px] text-muted">
                            {{ $m['intentos'] }} intentos · {{ $m['superadas'] }} superadas · {{ $m['falladas'] }} falladas
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Top participantes --}}
    <section class="panel p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-[15px] font-black text-ink">Mejores resultados</h2>
            <a href="{{ route('dashboard.resultados') }}" class="text-[11px] font-semibold text-muted hover:text-cyan">Ver todos</a>
        </div>

        @if ($ranking->isEmpty())
            <p class="mt-6 text-center text-[12.5px] text-muted">Sin resultados todavía.</p>
        @else
            <div class="mt-4 divide-y divide-[rgba(46,91,140,.25)]">
                @foreach ($ranking as $f)
                    <div class="flex items-center gap-3 py-2.5">
                        <span class="w-6 shrink-0 font-mono text-[13px] font-black"
                              style="color: {{ $f['puesto'] <= 3 ? $serie[0] : 'var(--color-muted)' }}">{{ $f['puesto'] }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[12.5px] font-bold text-ink">{{ $f['nombre'] }}</p>
                            <p class="truncate text-[10.5px] text-muted">{{ $f['area'] }}</p>
                        </div>
                        <span class="shrink-0 font-mono text-[12px] text-ink-soft">{{ $f['aciertos'] }}/{{ $f['total'] }}</span>
                        <span class="shrink-0 font-mono text-[12px] text-muted-2">{{ $f['tiempo'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-5 grid grid-cols-3 gap-2.5 border-t border-[rgba(46,91,140,.25)] pt-4">
            <div class="text-center">
                <p class="font-mono text-[17px] font-black text-ink">{{ $resumen['misiones'] }}</p>
                <p class="eyebrow mt-0.5 !text-[9px]">Jugables</p>
            </div>
            <div class="text-center">
                <p class="font-mono text-[17px] font-black text-muted">{{ $resumen['bloqueadas'] }}</p>
                <p class="eyebrow mt-0.5 !text-[9px]">Bloqueadas</p>
            </div>
            <div class="text-center">
                <p class="timer text-[17px]">{{ $resumen['tiempo_medio'] }}</p>
                <p class="eyebrow mt-0.5 !text-[9px]">Tiempo medio</p>
            </div>
        </div>
    </section>
</div>
@endsection
