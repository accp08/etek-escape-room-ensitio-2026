{{-- Ventana del código: el participante ya escaneó el QR y el
     cronómetro corre. El reto se resuelve en la sala; aquí solo se escribe
     el código que entrega la estación. El código correcto nunca se imprime. --}}
@extends('layouts.app')
@section('titulo', $mision->titulo)

@section('hud')
    @include('partials.hud-mision')
@endsection

@section('contenido')
<form method="POST" action="{{ route('mision.responder', $mision->slug) }}" id="form-mision"
      class="flex min-h-full flex-col px-5 pb-6 pt-6" autocomplete="off">
    @csrf

    <div class="anim-pop anim-ring mx-auto grid h-20 w-20 place-items-center rounded-full border-2 border-[rgba(0,229,255,.5)] bg-[rgba(0,229,255,.1)] text-cyan">
        @include('partials.icono', ['n' => $mision->icono, 'c' => 'w-10 h-10'])
    </div>

    <p class="eyebrow anim-in mt-5 text-center" style="--i:1">{{ txt('mision.codigo_titulo', 'Misión activa') }}</p>
    <h2 class="anim-in mt-1.5 text-center text-[22px] font-black leading-tight text-ink" style="--i:2">{{ $mision->titulo }}</h2>
    <p class="anim-in mx-auto mt-3 max-w-[34ch] text-center text-[13px] leading-relaxed text-muted" style="--i:3">
        {{ txt('mision.codigo_texto', 'Resuelve el reto en la estación. Cuando obtengas el código de la misión, escríbelo aquí antes de que el cronómetro llegue a cero.') }}
    </p>

    <div class="panel anim-in mt-7 p-5" style="--i:4">
        @if ($mision->pideCodigosPorNivel())
            {{-- Misión de clasificación: solo se piden los códigos configurados. --}}
            <div class="space-y-4">
                @foreach ($mision->codigosDeLaEstacion() as $posicion => $codigo)
                    <div>
                        <label for="codigo-{{ $posicion }}" class="field-label">{{ $codigo['nombre'] }}</label>
                        <input id="codigo-{{ $posicion }}" name="codigo[{{ $posicion }}]" class="field mt-1 text-center font-mono text-xl font-extrabold uppercase tracking-[.25em]"
                               maxlength="60" autocapitalize="characters" autocorrect="off" spellcheck="false"
                               value="{{ old('codigo.'.$posicion) }}" placeholder="· · · ·" @if ($loop->first) autofocus @endif>
                    </div>
                @endforeach
            </div>
        @else
            <label for="codigo" class="field-label">{{ txt('mision.codigo_campo', 'Código de la misión') }}</label>
            <input id="codigo" name="codigo" class="field mt-1 text-center font-mono text-2xl font-extrabold uppercase tracking-[.3em]"
                   maxlength="60" autocapitalize="characters" autocorrect="off" spellcheck="false"
                   value="{{ old('codigo') }}" placeholder="· · · ·" autofocus>
        @endif

        @error('codigo')
            <p class="mt-2 text-[12px] text-pink-soft">{{ $message }}</p>
        @enderror
    </div>
</form>
@endsection

@section('pie')
    @include('partials.pie-mision')
@endsection
