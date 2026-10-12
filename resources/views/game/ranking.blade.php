@extends('layouts.app')
@section('titulo', 'Ranking general')

@php
    $podio = $ranking->take(3);
    $yo    = $ranking->firstWhere(fn ($f) => $f['partida']->id === $partida->id);
@endphp

@section('contenido')
<div class="safe-top px-5 pb-8 pt-6">

    <div class="flex items-center justify-between">
        <a href="{{ route($partida->codigoIngresado() ? 'certificado' : 'hub') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-muted hover:text-cyan">
            @include('partials.icono', ['n' => 'back', 'c' => 'w-4 h-4'])
            Volver
        </a>
        <span class="badge">En vivo</span>
    </div>

    <div class="mt-5 text-center">
        <div class="anim-pop mb-5">
            @include('partials.logo', ['tam' => 'md'])
        </div>

        <div class="anim-pop mx-auto grid h-14 w-14 place-items-center rounded-2xl border border-[rgba(0,229,255,.35)] bg-[rgba(0,229,255,.1)] text-cyan" style="--i:1">
            @include('partials.icono', ['n' => 'trophy', 'c' => 'w-7 h-7'])
        </div>
        <h1 class="mt-4 text-[24px] font-black leading-tight text-ink">{{ txt('ranking.titulo', 'Ranking general') }}</h1>
        <p class="mx-auto mt-2 max-w-[30ch] text-[12px] leading-relaxed text-muted">{{ txt('ranking.criterio', '') }}</p>
    </div>

    @if ($ranking->isEmpty())
        <div class="panel mt-8 p-6 text-center">
            <p class="text-[13px] text-muted">Todavía no hay resultados registrados.</p>
        </div>
    @else
        {{-- Podio --}}
        @if ($podio->count() === 3)
            <div class="mt-7 grid grid-cols-3 items-end gap-2">
                @foreach ([1 => $podio[1], 0 => $podio[0], 2 => $podio[2]] as $idx => $p)
                    @php
                        $alturaBase = [0 => 'h-20', 1 => 'h-14', 2 => 'h-10'][$idx];
                        $aro = [
                            0 => 'border-[rgba(0,229,255,.6)] bg-[rgba(0,229,255,.12)] text-cyan',
                            1 => 'border-[rgba(155,213,255,.5)] bg-[rgba(155,213,255,.1)] text-sky',
                            2 => 'border-[rgba(255,45,161,.45)] bg-[rgba(255,45,161,.1)] text-pink-soft',
                        ][$idx];
                        $base = [
                            0 => 'border-[rgba(0,229,255,.45)] bg-[rgba(0,229,255,.09)]',
                            1 => 'border-[rgba(155,213,255,.32)] bg-[rgba(155,213,255,.06)]',
                            2 => 'border-[rgba(255,45,161,.32)] bg-[rgba(255,45,161,.06)]',
                        ][$idx];
                    @endphp
                    <div class="anim-in flex flex-col items-center" style="--i:{{ 3 - $idx }}">
                        <div class="grid h-12 w-12 place-items-center rounded-full border-2 {{ $aro }} text-base font-black">{{ $p['puesto'] }}</div>
                        <p class="mt-2 w-full truncate text-center text-[11px] font-bold text-ink">{{ $p['nombre'] }}</p>
                        <p class="font-mono text-[10px] text-muted">{{ $p['aciertos'] }} pts</p>
                        <p class="font-mono text-[10px] text-muted-2">{{ $p['tiempo'] }}</p>
                        <div class="mt-2 w-full rounded-t-xl border border-b-0 {{ $base }} {{ $alturaBase }}"></div>
                    </div>
                @endforeach
            </div>
            <div class="h-px bg-[rgba(46,91,140,.6)]"></div>
        @endif

        <div class="mt-6 flex items-center gap-3 px-3 pb-2">
            <span class="eyebrow w-6">#</span>
            <span class="eyebrow flex-1">Participante</span>
            <span class="eyebrow w-12 text-right">Pts</span>
            <span class="eyebrow w-12 text-right">Tiempo</span>
        </div>

        <div class="panel anim-in divide-y divide-[rgba(46,91,140,.3)] overflow-hidden" style="--i:5">
            @foreach ($ranking as $p)
                @php $esYo = $p['partida']->id === $partida->id; @endphp
                <div @class(['flex items-center gap-3 px-3 py-3', 'bg-[rgba(0,229,255,.07)]' => $esYo])>
                    <span @class([
                        'w-6 shrink-0 font-mono text-[13px] font-black',
                        'text-cyan' => $p['puesto'] <= 3,
                        'text-muted' => $p['puesto'] > 3,
                    ])>{{ $p['puesto'] }}</span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-bold text-ink">
                            {{ $p['nombre'] }}
                            @if ($esYo)<span class="badge ml-1 !px-1.5 !py-0 !text-[9px]">Tú</span>@endif
                        </p>
                        <p class="truncate text-[10.5px] text-muted">{{ $p['area'] }}</p>
                    </div>

                    <span class="w-12 shrink-0 text-right font-mono text-[12px] font-bold text-ink-soft">
                        {{ $p['aciertos'] }}<span class="text-muted">/{{ $p['total'] }}</span>
                    </span>
                    <span class="w-12 shrink-0 text-right font-mono text-[12px] text-muted-2">{{ $p['tiempo'] }}</span>
                </div>
            @endforeach
        </div>

        @unless ($yo)
            <p class="mt-4 text-center text-[11px] text-muted">
                Tu partida aparecerá aquí cuando sumes tus primeros aciertos.
            </p>
        @endunless
    @endif
</div>
@endsection
