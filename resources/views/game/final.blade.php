{{-- Código de recuperación en el celular. Aquí solo se muestra: se digita
     en la consola que se proyecta en la sala (/consola). Cuando la consola lo
     acepta, esta pantalla pasa sola a la victoria. --}}
@extends('layouts.app')
@section('titulo', 'Código de recuperación')

@php
    $jugables  = $tablero->filter(fn ($f) => $f['mision']->jugable);
    $obtenidas = $jugables->where('estado', 'superada');
@endphp

@section('contenido')
<div class="safe-top flex min-h-full flex-col px-6 pb-8 pt-8"
     @if ($completo) data-espera-consola="{{ route('final.estado') }}" data-victoria="{{ route('victoria') }}" @endif>

    <a href="{{ route('hub') }}" class="mb-6 inline-flex items-center gap-2 self-start text-xs font-semibold text-muted hover:text-cyan">
        @include('partials.icono', ['n' => 'back', 'c' => 'w-4 h-4'])
        Centro de mando
    </a>

    <div class="flex-1">
        <p class="eyebrow">Consola de recuperación</p>
        <h1 class="mt-1.5 text-[26px] font-black leading-tight text-ink">{{ txt('final.titulo', 'Revierte el ataque') }}</h1>
        <p class="mt-3 text-sm leading-relaxed text-muted">{{ txt('final.descripcion', '') }}</p>

        @if ($completo)
            {{-- Código completo: se lleva a la consola de la sala --}}
            <div class="panel scan anim-pop mt-6 p-5 text-center">
                <p class="eyebrow">Tu código de recuperación</p>
                <p class="sr-only">{{ $obtenido }}</p>

                {{-- Un bloque por misión, en orden: así se lee y se dicta fácil. --}}
                <div class="mt-4 grid gap-2" style="grid-template-columns: repeat({{ max(1, $jugables->count()) }}, minmax(0,1fr))" aria-hidden="true">
                    @foreach ($jugables as $fila)
                        <div>
                            <div class="code-slot !aspect-auto py-3 text-[22px] !text-cyan" data-filled="true">{{ $fila['fragmento'] }}</div>
                            <p class="eyebrow mt-1.5 truncate !text-[8.5px]">{{ \Illuminate\Support\Str::of($fila['mision']->etiqueta ?: 'M' . $loop->iteration)->limit(9, '') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="aviso aviso-ok anim-in mt-5" style="--i:2">
                @include('partials.icono', ['n' => 'shield', 'c' => 'w-4 h-4 shrink-0 mt-px'])
                <span>{{ txt('final.ir_consola', 'Ve a la consola de recuperación de la sala y digita tu código para revertir el ataque.') }}</span>
            </div>

            <p class="anim-in mt-4 flex items-center justify-center gap-2 text-[11.5px] text-muted" style="--i:3">
                <span class="h-2 w-2 animate-pulse rounded-full bg-[color:var(--color-cyan)]"></span>
                Esperando a que lo digites en la consola…
            </p>
        @else
            {{-- Aún faltan misiones: se ve el avance --}}
            <p class="eyebrow mt-6 mb-2.5">Fragmentos recuperados</p>

            <div class="anim-in grid gap-2" style="--i:2; grid-template-columns: repeat({{ max(1, $jugables->count()) }}, minmax(0,1fr))">
                @foreach ($jugables as $fila)
                    <div class="text-center">
                        <div class="code-slot text-base" data-filled="{{ $fila['estado'] === 'superada' ? 'true' : 'false' }}">
                            {{ $fila['estado'] === 'superada' ? $fila['fragmento'] : str_repeat('·', \App\Models\CodigoMision::largo()) }}
                        </div>
                        <p class="eyebrow mt-1.5 !text-[9px]">{{ \Illuminate\Support\Str::of($fila['mision']->etiqueta ?: 'M' . $loop->iteration)->limit(8, '') }}</p>
                    </div>
                @endforeach
            </div>

            <p class="mt-5 text-center text-[12px] leading-relaxed text-muted">
                Te faltan <strong class="text-ink-soft">{{ $jugables->count() - $obtenidas->count() }}</strong>
                {{ $jugables->count() - $obtenidas->count() === 1 ? 'misión' : 'misiones' }} por superar.
                Con todos los fragmentos tendrás tu código completo.
            </p>
        @endif
    </div>
</div>
@endsection
