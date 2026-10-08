@extends('layouts.app')
@section('titulo', 'Código de recuperación')

@php
    $jugables  = $tablero->filter(fn ($f) => $f['mision']->jugable);
    $obtenidas = $jugables->where('estado', 'superada');
@endphp

@section('contenido')
<div class="safe-top flex min-h-full flex-col px-6 pb-8 pt-8">

    <a href="{{ route('hub') }}" class="mb-6 inline-flex items-center gap-2 self-start text-xs font-semibold text-muted hover:text-cyan">
        @include('partials.icono', ['n' => 'back', 'c' => 'w-4 h-4'])
        Centro de mando
    </a>

    <div class="flex-1">
        <p class="eyebrow">Consola de recuperación</p>
        <h1 class="mt-1.5 text-[26px] font-black leading-tight text-ink">{{ txt('final.titulo', 'Revierte el ataque') }}</h1>
        <p class="mt-3 text-sm leading-relaxed text-muted">{{ txt('final.descripcion', '') }}</p>

        <div class="panel scan anim-in mt-6 overflow-hidden" style="--i:1">
            <div class="flex items-center gap-1.5 border-b border-[rgba(46,91,140,.4)] bg-[rgba(5,12,26,.7)] px-3.5 py-2.5">
                <span class="h-2.5 w-2.5 rounded-full bg-[color:var(--color-pink)]"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-[color:var(--color-sky)]"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-[color:var(--color-ok)]"></span>
                <span class="ml-2 font-mono text-[10px] text-muted">recovery@core — /decrypt</span>
            </div>

            <div class="space-y-1 p-3.5 font-mono text-[11px] leading-relaxed">
                <p class="text-muted">$ scan --systems</p>
                <p class="text-pink-soft">! volúmenes cifrados · integridad {{ $jugables->count() ? round($obtenidas->count() * 100 / $jugables->count()) : 0 }}%</p>
                <p class="text-muted">$ recovery --assemble</p>
                <p class="text-sky">→ {{ $obtenidas->count() }} de {{ $jugables->count() }} fragmentos recuperados</p>
            </div>
        </div>

        <p class="eyebrow mt-6 mb-2.5">Fragmentos recuperados</p>

        <div class="anim-in grid gap-2" style="--i:2; grid-template-columns: repeat({{ max(1, $jugables->count()) }}, minmax(0,1fr))">
            @foreach ($jugables as $fila)
                <div class="text-center">
                    <div class="code-slot text-base" data-filled="{{ $fila['estado'] === 'superada' ? 'true' : 'false' }}">
                        {{ $fila['estado'] === 'superada' ? $fila['fragmento'] : '··' }}
                    </div>
                    <p class="eyebrow mt-1.5 !text-[9px]">{{ \Illuminate\Support\Str::of($fila['mision']->etiqueta ?: 'M' . $loop->iteration)->limit(8, '') }}</p>
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('final.verificar') }}" class="panel-flat anim-in mt-6 p-4" style="--i:3">
            @csrf

            @if ($errors->any())
                <div class="aviso aviso-error mb-3">
                    @include('partials.icono', ['n' => 'alert', 'c' => 'w-4 h-4 shrink-0 mt-px'])
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <label for="codigo" class="field-label">Código completo de recuperación</label>
            <input id="codigo" name="codigo" type="text"
                   class="field text-center font-mono text-lg font-black uppercase tracking-[6px]"
                   placeholder="{{ str_repeat('·', max(2, $jugables->count() * (int) config('escape_room.largo_fragmento', 3))) }}"
                   maxlength="40" autocomplete="off" required>

            <p class="mt-2.5 text-[11px] leading-relaxed text-muted">
                Ingresa los {{ $jugables->count() }} fragmentos seguidos, sin espacios.
            </p>

            <button type="submit" class="btn btn-danger mt-4">
                @include('partials.icono', ['n' => 'lock', 'c' => 'w-4 h-4'])
                {{ txt('final.boton', 'Ejecutar recuperación') }}
            </button>
        </form>

        @if ($obtenidas->count() < $jugables->count())
            <p class="mt-4 text-center text-[11px] text-muted">
                Te faltan {{ $jugables->count() - $obtenidas->count() }} misiones por superar.
            </p>
        @endif
    </div>
</div>
@endsection
