@extends('layouts.dashboard')
@section('seccion', 'Contenido · Misiones')
@section('titulo', $mision->titulo)

@php
    $iconos = ['mail','key','folder','search','phone','lock','shield','alert','clock','trophy','file','user','camera','bug','network','coin','ia'];
@endphp

@section('contenido')

<a href="{{ route('dashboard.misiones') }}" class="mb-4 inline-flex items-center gap-2 text-xs font-semibold text-muted hover:text-cyan">
    @include('partials.icono', ['n' => 'back', 'c' => 'w-4 h-4'])
    Todas las misiones
</a>

{{-- Parámetros de la misión --}}
<form method="POST" action="{{ route('dashboard.misiones.actualizar', $mision) }}" enctype="multipart/form-data" class="panel p-5">
    @csrf @method('PUT')

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="eyebrow">Misión en sitio</p>
            <h2 class="mt-1 text-[18px] font-black text-ink">{{ $mision->titulo }}</h2>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="badge badge-muted">Fragmento único por participante</span>
            <a href="{{ route('dashboard.misiones.proyectar', $mision) }}" target="_blank" class="btn btn-primary !w-auto !py-2.5">
                @include('partials.icono', ['n' => 'play', 'c' => 'w-4 h-4'])
                Proyectar en pantalla
            </a>
        </div>
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-2">
        <div>
            <label class="field-label">Título</label>
            <input name="titulo" class="field" value="{{ old('titulo', $mision->titulo) }}" maxlength="120" required>
        </div>
        <div>
            <label class="field-label">Etiqueta <span class="font-normal normal-case tracking-normal text-muted">(opcional)</span></label>
            <input name="etiqueta" class="field" value="{{ old('etiqueta', $mision->etiqueta) }}" maxlength="60">
            <p class="mt-1.5 text-[11px] text-muted">Texto pequeño sobre el título. Si lo dejas vacío no se muestra.</p>
        </div>

        <div>
            <label class="field-label">Icono</label>
            <select name="icono" class="field">
                @foreach ($iconos as $ic)
                    <option value="{{ $ic }}" @selected($mision->icono === $ic)>{{ $ic }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="field-label">Duración (segundos)</label>
            <input name="duracion" type="number" class="field" value="{{ old('duracion', $mision->duracion) }}" min="30" max="3600" required>
        </div>
        <div>
            <label class="field-label">Oportunidades</label>
            <input name="intentos" type="number" class="field" value="{{ old('intentos', $mision->intentos) }}" min="0" max="99" required>
            <p class="mt-1.5 text-[11px] text-muted">0 = ilimitadas. Cada una tiene su propio cronómetro y los tiempos se suman.</p>
        </div>

        <div>
            <label class="field-label">Orden</label>
            <input name="orden" type="number" class="field" value="{{ old('orden', $mision->orden) }}" min="0" max="99" required>
        </div>

        @if ($mision->pideCodigosPorNivel())
            <div class="md:col-span-2">
                <label class="field-label">Códigos de la estación</label>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (\App\Models\Mision::NIVELES_CLASIFICACION as $nivel => $nombre)
                        <div>
                            <label for="codigo-{{ $nivel }}" class="mb-1 block text-[11px] font-semibold text-ink-soft">{{ $nombre }}</label>
                            <input id="codigo-{{ $nivel }}" name="codigos_clasificacion[{{ $nivel }}]" class="field font-mono uppercase" maxlength="40" autocomplete="off"
                                   value="{{ old('codigos_clasificacion.'.$nivel, $mision->codigoDeNivel($nivel)) }}" placeholder="Ej. 4F7K2">
                        </div>
                    @endforeach
                </div>
                <p class="mt-1.5 text-[11px] leading-relaxed text-muted">
                    Uno por nivel de clasificación. El participante debe escribir los cuatro correctos para superar la misión.
                    No distinguen mayúsculas, espacios ni guiones. Si falta alguno, nadie puede superarla.
                </p>
            </div>
        @else
            <div class="md:col-span-2">
                <label class="field-label">Código de la estación</label>
                <input name="codigo_presencial" class="field font-mono uppercase" maxlength="40" autocomplete="off"
                       value="{{ old('codigo_presencial', $mision->codigo_presencial) }}" placeholder="Ej. 4F7K2">
                <p class="mt-1.5 text-[11px] leading-relaxed text-muted">
                    El que el participante obtiene al resolver el reto físico. No distingue mayúsculas, espacios ni guiones.
                    Sin código, nadie puede superar la misión.
                </p>
            </div>
        @endif
    </div>

    <div class="mt-4">
        <label class="field-label">Introducción (historia de la misión)</label>
        <textarea name="brief" class="field" rows="3" maxlength="800">{{ old('brief', $mision->brief) }}</textarea>
    </div>

    <div class="mt-4">
        <label class="field-label">Instrucciones del reto</label>
        <p class="mb-1.5 text-[11px] text-muted">Se muestran en la pantalla de proyección y en el celular durante la misión.</p>
        <div class="editor" data-editor data-vacio="Qué debe hacer en esta misión…">
            <textarea name="instruccion" data-editor-valor hidden>{{ old('instruccion', $mision->instruccion) }}</textarea>
        </div>
    </div>

    <div class="mt-4">
        <label class="field-label">Audio de la pantalla de proyección <span class="font-normal normal-case tracking-normal text-muted">(opcional)</span></label>
        <p class="mb-1.5 text-[11px] text-muted">MP3 de hasta {{ intdiv(\App\Support\Audio::MAX_KB, 1024) }} MB. Suena en bucle en el televisor de la estación.</p>

        @if ($audio = $mision->audioUrl())
            <div class="mb-2.5 flex flex-wrap items-center gap-3">
                <audio controls preload="none" src="{{ $audio }}" class="h-9 max-w-full"></audio>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="quitar_audio" value="1"
                           class="h-4 w-4 rounded border-[rgba(46,91,140,.7)] bg-[rgba(5,12,26,.85)] accent-[color:var(--color-cyan)]">
                    <span class="text-[12px] text-ink-soft">Quitar audio</span>
                </label>
            </div>
        @endif

        <input type="file" name="audio" accept=".mp3,audio/mpeg" class="field">
        @if ($audio)
            <p class="mt-1.5 text-[11px] text-muted">Sube otro archivo para reemplazar el actual.</p>
        @endif
    </div>

    <div class="mt-4 flex flex-wrap gap-5">
        <label class="flex items-center gap-2.5">
            <input type="checkbox" name="jugable" value="1" @checked($mision->jugable)
                   class="h-4 w-4 rounded border-[rgba(46,91,140,.7)] bg-[rgba(5,12,26,.85)] accent-[color:var(--color-cyan)]">
            <span class="text-[12px] text-ink-soft">Jugable <span class="text-muted">(cuenta para el puntaje)</span></span>
        </label>

        <label class="flex items-center gap-2.5">
            <input type="checkbox" name="visible" value="1" @checked($mision->visible)
                   class="h-4 w-4 rounded border-[rgba(46,91,140,.7)] bg-[rgba(5,12,26,.85)] accent-[color:var(--color-cyan)]">
            <span class="text-[12px] text-ink-soft">Visible <span class="text-muted">(aparece en el centro de mando)</span></span>
        </label>

        <label class="flex items-center gap-2.5">
            <input type="checkbox" name="codigo_cualquier_orden" value="1" @checked(old('codigo_cualquier_orden', $mision->codigo_cualquier_orden))
                   class="h-4 w-4 rounded border-[rgba(46,91,140,.7)] bg-[rgba(5,12,26,.85)] accent-[color:var(--color-cyan)]">
            <span class="text-[12px] text-ink-soft">Código en cualquier orden <span class="text-muted">(si es 123, también vale 213, 321…)</span></span>
        </label>
    </div>

    <button type="submit" class="btn btn-primary mt-6">Guardar misión</button>
</form>

{{-- QR de la estación --}}
<section class="panel mt-4 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="eyebrow">Estación</p>
            <h2 class="mt-1 text-[18px] font-black text-ink">QR de la estación</h2>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-start gap-5">
        <div class="rounded-xl bg-white p-3">
            <canvas data-qr="{{ $mision->urlActivacion() }}" data-qr-nombre="qr-{{ $mision->slug }}" width="180" height="180"></canvas>
        </div>

        <div class="min-w-0 flex-1 space-y-3 text-[12.5px] leading-relaxed text-muted">
            <p>
                Proyéctalo en el televisor de la estación o imprímelo. Al escanearlo, la misión se activa
                para el participante y arranca el cronómetro; luego escribe el código del reto.
            </p>
            @unless ($mision->tieneCodigo())
                <p class="aviso aviso-error">Esta misión aún no tiene código: nadie podrá superarla.</p>
            @endunless
            <p class="break-all font-mono text-[10.5px]">{{ $mision->urlActivacion() }}</p>

            <div class="flex flex-wrap gap-2.5">
                <a href="{{ route('dashboard.misiones.proyectar', $mision) }}" target="_blank" class="btn btn-primary !w-auto">Proyectar en pantalla</a>
                <button type="button" class="btn btn-ghost !w-auto" data-qr-descargar="qr-{{ $mision->slug }}">Descargar PNG</button>
                <a href="{{ route('dashboard.misiones.qr') }}" target="_blank" class="btn btn-ghost !w-auto">Hoja para imprimir</a>
                <form method="POST" action="{{ route('dashboard.misiones.qr.regenerar', $mision) }}"
                      onsubmit="return confirm('El QR impreso o proyectado dejará de funcionar. ¿Regenerar?')">
                    @csrf
                    <button type="submit" class="btn btn-danger !w-auto">Regenerar QR</button>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
