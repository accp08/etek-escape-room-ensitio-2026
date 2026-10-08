{{-- Jugadores en línea: se pinta dentro del dashboard y el navegador lo vuelve a pedir solo. --}}
@php($maxLugar = max(1, $enLinea['escenarios']->max('cantidad')))

<div class="flex flex-wrap items-baseline justify-between gap-2">
    <h2 class="flex items-center gap-2 text-[15px] font-black text-ink">
        <span class="en-linea-punto" data-activo="{{ $enLinea['total'] > 0 ? 'si' : 'no' }}"></span>
        Jugadores en línea
    </h2>
    <span class="eyebrow">Activos en los últimos {{ \App\Services\Estadisticas::MINUTOS_EN_LINEA }} min · se actualiza solo</span>
</div>

<div class="mt-5 grid gap-5 lg:grid-cols-[200px_1fr] xl:grid-cols-[220px_1.2fr_1fr]">

    {{-- Cifras --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-1">
        <div class="kpi" style="--tono: var(--color-ink)">
            <p class="kpi-valor">{{ $enLinea['total'] }}</p>
            <p class="kpi-etiqueta">{{ $enLinea['total'] === 1 ? 'Persona conectada' : 'Personas conectadas' }}</p>
        </div>
        <div class="kpi" style="--tono: var(--color-cyan)">
            <p class="kpi-valor">{{ $enLinea['resolviendo'] }}</p>
            <p class="kpi-etiqueta">Resolviendo una misión</p>
        </div>
    </div>

    {{-- Por escenario --}}
    <div>
        <p class="eyebrow">Por escenario</p>
        <div class="mt-3 space-y-3">
            @foreach ($enLinea['escenarios'] as $escenario)
                <div>
                    <div class="mb-1.5 flex items-baseline justify-between gap-3">
                        <span @class([
                            'min-w-0 truncate text-[12.5px]',
                            'font-bold text-ink-soft' => $escenario['mision'],
                            'text-muted' => ! $escenario['mision'],
                        ])>{{ $escenario['nombre'] }}</span>
                        <span class="shrink-0 font-mono text-[12px] font-bold {{ $escenario['cantidad'] > 0 ? 'text-ink' : 'text-muted' }}">{{ $escenario['cantidad'] }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-[rgba(5,12,26,.8)]">
                        <div class="h-full rounded-full transition-all"
                             style="width: {{ round($escenario['cantidad'] * 100 / $maxLugar) }}%; background: {{ $escenario['mision'] ? '#009fb8' : '#6a5cff' }}"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Quién está y dónde --}}
    <div class="lg:col-span-2 xl:col-span-1">
        <p class="eyebrow">Quién está y dónde</p>

        @if ($enLinea['jugadores']->isEmpty())
            <p class="mt-6 text-center text-[12.5px] text-muted">No hay nadie jugando en este momento.</p>
        @else
            <div class="mt-2 max-h-[320px] divide-y divide-[rgba(46,91,140,.25)] overflow-y-auto pr-1">
                @foreach ($enLinea['jugadores'] as $jugador)
                    <div class="flex items-start justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-[12.5px] font-bold text-ink">{{ $jugador['nombre'] }}</p>
                            @if ($jugador['area'])
                                <p class="truncate text-[10.5px] text-muted">{{ $jugador['area'] }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-[12px] {{ $jugador['resolviendo'] ? 'font-bold text-cyan' : 'text-ink-soft' }}">{{ $jugador['lugar'] }}</p>
                            <p class="font-mono text-[9.5px] text-muted">{{ $jugador['visto_en']->diffForHumans(short: true) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
