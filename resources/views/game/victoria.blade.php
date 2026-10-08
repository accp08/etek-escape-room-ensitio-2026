@extends('layouts.app')
@section('titulo', 'Amenaza contenida')

@php $jugables = $tablero->filter(fn ($f) => $f['mision']->jugable); @endphp

@section('contenido')
<div class="safe-top flex min-h-full flex-col px-6 pb-8 pt-12 text-center">

    <div class="flex-1">
        <div class="anim-pop mx-auto grid h-28 w-28 place-items-center rounded-full border-2 border-[rgba(34,197,94,.5)] bg-[rgba(34,197,94,.1)] text-ok">
            @include('partials.icono', ['n' => 'shield', 'c' => 'w-14 h-14'])
        </div>

        <p class="eyebrow mt-7">Protocolo completado</p>
        <h1 class="title-glow anim-in mt-2 text-[32px] leading-[1.1]" style="--i:2">
            {{ txt('victoria.titulo', 'Amenaza contenida') }}
        </h1>

        <p class="mx-auto mt-4 max-w-[32ch] text-sm leading-relaxed text-muted">
            {{ txt('victoria.texto', '') }}
        </p>

        <div class="panel scan anim-in mt-7 p-5" style="--i:3">
            <p class="eyebrow">Código de recuperación</p>
            <p class="code-chip mt-3 inline-block text-2xl">{{ $codigo }}</p>
        </div>

        <div class="anim-in mt-4 grid grid-cols-3 gap-2.5" style="--i:4">
            <div class="panel-flat p-3.5">
                <p class="text-xl font-black text-ok">{{ $partida->aciertos }}</p>
                <p class="eyebrow mt-1">Aciertos</p>
            </div>
            <div class="panel-flat p-3.5">
                <p class="timer text-xl">{{ $partida->tiempoLegible() }}</p>
                <p class="eyebrow mt-1">Tiempo</p>
            </div>
            <div class="panel-flat p-3.5">
                <p class="text-xl font-black text-cyan">{{ $partida->aciertos_posibles }}</p>
                <p class="eyebrow mt-1">Posibles</p>
            </div>
        </div>

        <div class="panel-flat anim-in mt-4 divide-y divide-[rgba(46,91,140,.3)] p-1">
            @foreach ($jugables as $fila)
                @php $avance = $fila['avance']; @endphp
                <div class="flex items-center gap-3 px-3 py-2.5 text-left">
                    <span class="{{ $fila['estado'] === 'superada' ? 'text-ok' : 'text-pink-soft' }}">
                        @include('partials.icono', ['n' => $fila['estado'] === 'superada' ? 'check' : 'x', 'c' => 'w-4 h-4'])
                    </span>
                    <p class="min-w-0 flex-1 truncate text-[12px] font-semibold text-ink-soft">{{ $fila['mision']->titulo }}</p>
                    <span class="font-mono text-[11px] text-muted">{{ $avance?->aciertos }}/{{ $avance?->aciertos_posibles }}</span>
                    @if ($fila['estado'] === 'superada')
                        <span class="code-chip !px-2 !py-0.5 text-[10px] !tracking-[2px]">{{ $fila['fragmento'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-8 space-y-3">
        <a href="{{ route('certificado') }}" class="btn btn-primary">
            @include('partials.icono', ['n' => 'download', 'c' => 'w-4 h-4'])
            {{ txt('victoria.certificado', 'Generar mi certificado') }}
        </a>

        <a href="{{ route('ranking') }}" class="btn btn-ghost">
            @include('partials.icono', ['n' => 'trophy', 'c' => 'w-4 h-4'])
            Ver ranking general
        </a>

        <form method="POST" action="{{ route('salir') }}">
            @csrf
            <button type="submit" class="block w-full text-center text-[11px] font-semibold text-muted hover:text-cyan">Salir</button>
        </form>
    </div>
</div>
@endsection
