@extends('layouts.dashboard')
@section('seccion', 'Configuración')
@section('titulo', 'Parámetros')

@php $v = fn ($c, $d = '') => old($c, $ajustes[$c]->valor ?? $d); @endphp

@section('contenido')
<form method="POST" action="{{ route('dashboard.ajustes.guardar') }}" class="grid gap-4 md:grid-cols-2">
    @csrf @method('PUT')

    <section class="panel p-5">
        <p class="eyebrow">Reglas</p>
        <h2 class="mt-1 text-[18px] font-black text-ink">Parámetros del juego</h2>

        <div class="mt-5 space-y-4">
            <div>
                <label class="field-label">Requisito para obtener cada fragmento</label>
                <p class="mt-1.5 text-[11px] leading-relaxed text-muted">
                    Escribir el código correcto de la estación antes de que el cronómetro llegue a cero.
                    El cronómetro arranca al escanear el QR.
                </p>
            </div>

            <div>
                <label class="field-label">Duración por defecto (segundos)</label>
                <input name="duracion_mision" type="number" class="field" min="30" max="3600"
                       value="{{ $v('duracion_mision', 180) }}" required>
                <p class="mt-1.5 text-[11px] text-muted">Cada misión puede tener su propia duración.</p>
            </div>

            <div>
                <label class="field-label">Oportunidades por misión</label>
                <input name="intentos_por_mision" type="number" class="field" min="0" max="99"
                       value="{{ $v('intentos_por_mision', 2) }}" required>
                <p class="mt-1.5 text-[11px] leading-relaxed text-muted">
                    0 = ilimitadas. Cada oportunidad tiene su propio cronómetro y los tiempos
                    se suman. Al agotarlas la misión queda fallada y se reabre al terminar
                    las demás, una sola vez. Cada misión puede tener su propio valor.
                </p>
            </div>
        </div>
    </section>

    <section class="panel h-fit p-5">
        <p class="eyebrow">Identidad</p>
        <h2 class="mt-1 text-[18px] font-black text-ink">Nombre de la experiencia</h2>

        <div class="mt-5 space-y-4">
            <div>
                <label class="field-label">Título</label>
                <input name="juego_titulo" class="field" maxlength="80" value="{{ $v('juego_titulo') }}" required>
            </div>
            <div>
                <label class="field-label">Subtítulo</label>
                <input name="juego_subtitulo" class="field" maxlength="120" value="{{ $v('juego_subtitulo') }}">
            </div>
            <div>
                <label class="field-label">Video de cierre</label>
                <input name="video_final" class="field" maxlength="160" value="{{ $v('video_final') }}"
                       placeholder="escape.mp4">
                <p class="mt-1.5 text-[11px] leading-relaxed text-muted">
                    Archivo dentro de <code class="font-mono">public/</code>. Déjalo vacío para ocultar el botón.
                </p>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mt-6">Guardar parámetros</button>
    </section>
</form>
@endsection
