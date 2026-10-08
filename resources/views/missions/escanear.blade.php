{{-- Antesala de la misión: el reto se juega en la sala. Aquí
     solo se escanea el QR de la estación; leerlo activa la misión y arranca
     el cronómetro. El QR lleva a mision.activar, igual si se lee con este
     lector que con la cámara del celular. --}}
@extends('layouts.app')
@section('titulo', $mision->titulo)

@section('contenido')
<div class="safe-top flex min-h-full flex-col px-6 pb-8 pt-8">

    <a href="{{ route('hub') }}" class="mb-6 inline-flex items-center gap-2 self-start text-xs font-semibold text-muted hover:text-cyan">
        @include('partials.icono', ['n' => 'back', 'c' => 'w-4 h-4'])
        Centro de mando
    </a>

    <div class="flex-1">
        <div class="anim-pop anim-ring grid h-16 w-16 place-items-center rounded-2xl border border-[rgba(0,229,255,.35)] bg-[rgba(0,229,255,.1)] text-cyan">
            @include('partials.icono', ['n' => $mision->icono, 'c' => 'w-8 h-8'])
        </div>

        @if ($mision->etiqueta)
            <p class="eyebrow anim-in mt-5" style="--i:1">{{ $mision->etiqueta }}</p>
        @endif
        <h1 @class(['anim-in text-[28px] font-black leading-[1.1] text-ink', $mision->etiqueta ? 'mt-1.5' : 'mt-5']) style="--i:2">{{ $mision->titulo }}</h1>

        @if ($mision->brief)
            <p class="anim-in mt-4 text-sm leading-relaxed text-muted" style="--i:3">{{ $mision->brief }}</p>
        @endif

        @if ($avance->reactivada)
            <div class="aviso aviso-error mt-4">
                @include('partials.icono', ['n' => 'alert', 'c' => 'w-4 h-4 shrink-0 mt-px'])
                <span>Esta es tu <strong>segunda y última oportunidad</strong> en esta misión.</span>
            </div>
        @endif

        @error('qr')
            <div class="aviso aviso-error mt-4">
                @include('partials.icono', ['n' => 'alert', 'c' => 'w-4 h-4 shrink-0 mt-px'])
                <span>{{ $message }}</span>
            </div>
        @enderror

        @if (trim(strip_tags($mision->instruccion ?? '')) !== '')
            <div class="panel-flat anim-in mt-6 p-4" style="--i:4">
                <p class="eyebrow">{{ txt('mision.objetivo', 'Tu objetivo') }}</p>
                <div class="mt-2 text-[13px] leading-relaxed text-ink-soft">{!! $mision->instruccion !!}</div>
            </div>
        @endif

        <div class="anim-in mt-4 grid grid-cols-3 gap-2.5" style="--i:4">
            <div class="panel-flat p-3 text-center">
                <p class="timer text-lg">{{ sprintf('%02d:%02d', intdiv($mision->duracion, 60), $mision->duracion % 60) }}</p>
                <p class="eyebrow mt-1">Tiempo</p>
            </div>
            <div class="panel-flat p-3 text-center">
                <p class="text-lg font-black text-ink">{{ $avance->intentosRestantesLegible() }}</p>
                <p class="eyebrow mt-1">Intentos</p>
            </div>
            <div class="panel-flat p-3 text-center">
                <p class="code-chip !inline-block !px-2 !py-0.5 text-sm">{{ str_repeat('·', \App\Models\CodigoMision::largo()) }}</p>
                <p class="eyebrow mt-1">Código</p>
            </div>
        </div>

        @if ($avance->segundos > 0)
            <p class="mt-4 text-[11px] leading-relaxed text-muted">
                Tiempo acumulado en esta misión:
                <strong class="text-ink-soft">{{ sprintf('%02d:%02d', intdiv($avance->segundos, 60), $avance->segundos % 60) }}</strong>
            </p>
        @endif

        {{-- Lector de QR --}}
        <div class="panel anim-in mt-6 p-4" style="--i:5" data-escaner>
            <p class="eyebrow">{{ txt('mision.escanear_titulo', 'Escanea el código QR de la estación') }}</p>
            <p class="mt-2 text-[13px] leading-relaxed text-ink-soft">
                {{ txt('mision.escanear_texto', 'Ubica el código QR de esta misión en la sala y escanéalo. El cronómetro arranca en cuanto lo leas.') }}
            </p>

            <div class="scan relative mt-4 hidden aspect-square overflow-hidden rounded-2xl border border-[rgba(0,229,255,.45)] bg-black" data-escaner-visor>
                <video class="h-full w-full object-cover" playsinline muted data-escaner-video></video>
                <div class="pointer-events-none absolute inset-[14%] rounded-2xl border-2 border-[rgba(0,229,255,.8)] shadow-[0_0_0_999px_rgba(3,7,16,.45)]"></div>
            </div>

            <p class="mt-3 hidden text-[12px] leading-relaxed text-pink-soft" data-escaner-estado role="status"></p>

            <p class="mt-3 text-[11px] leading-relaxed text-muted">
                {{ txt('mision.escanear_ayuda', '¿La cámara no abre? Escanea el QR con la cámara de tu celular: te traerá de vuelta aquí.') }}
            </p>
        </div>
    </div>

    <div class="mt-7 space-y-2.5">
        <button type="button" class="btn btn-primary" data-escaner-abrir>
            @include('partials.icono', ['n' => 'camera', 'c' => 'w-4 h-4'])
            {{ txt('mision.escanear_boton', 'Escanear código QR') }}
        </button>
        <button type="button" class="btn btn-ghost hidden" data-escaner-cerrar>Cancelar</button>
    </div>
</div>
@endsection
