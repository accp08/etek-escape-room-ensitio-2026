@extends('layouts.dashboard')
@section('seccion', 'Configuración')
@section('titulo', 'Empresa')

@section('contenido')
<div>
    {{-- Formulario --}}
    <form method="POST" action="{{ route('dashboard.empresa.actualizar') }}"
          enctype="multipart/form-data" class="grid gap-4 md:grid-cols-[1fr_320px]">
        @csrf
        @method('PUT')

        {{-- Datos --}}
        <section class="panel anim-in p-5" style="--i:2">
            <p class="eyebrow">Identidad de la empresa</p>
            <h2 class="mt-1 text-[18px] font-black text-ink">Nombre y logo</h2>
            <p class="mt-2 text-[12.5px] leading-relaxed text-muted">
                Se aplican de inmediato en el acceso al juego, el ranking y el certificado
                de participación.
            </p>

            <div class="mt-5">
                <label for="nombre" class="field-label">Nombre de la empresa</label>
                <input id="nombre" name="nombre" type="text" class="field" maxlength="80"
                       value="{{ old('nombre', $marca['nombre']) }}" placeholder="Nombre que verán los participantes" required>
                <p class="mt-1.5 text-[11px] text-muted">Aparece en el acceso y firma el certificado.</p>
            </div>

            <div class="mt-5">
                <span class="field-label">Logo</span>

                <label for="logo" class="zona-archivo" data-zona-logo>
                    <span class="mx-auto mb-2.5 grid h-11 w-11 place-items-center rounded-xl border border-[rgba(0,229,255,.35)] bg-[rgba(0,229,255,.08)] text-cyan">
                        @include('partials.icono', ['n' => 'download', 'c' => 'w-5 h-5 rotate-180'])
                    </span>
                    <span class="block text-[13px] font-bold text-ink-soft" data-nombre-archivo>
                        Selecciona un archivo
                    </span>
                    <span class="mt-1 block text-[11px] text-muted">
                        PNG, JPG, WEBP o SVG · hasta 2 MB
                    </span>
                    <input id="logo" name="logo" type="file" class="sr-only-c"
                           accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml">
                </label>

                <p class="mt-2.5 text-[11px] leading-relaxed text-muted">
                    Usa preferiblemente un <strong class="text-ink-soft">SVG con fondo transparente</strong>:
                    se ve nítido en el certificado impreso. El logo siempre se muestra sobre una
                    placa blanca, así que sirve cualquier color y cualquier proporción.
                </p>
            </div>

            <button type="submit" class="btn btn-primary mt-6">
                @include('partials.icono', ['n' => 'check', 'c' => 'w-4 h-4'])
                Guardar cambios
            </button>
        </section>

        {{-- Vista previa --}}
        <aside class="panel anim-in h-fit p-5" style="--i:3">
            <p class="eyebrow">Vista previa</p>
            <p class="mt-1.5 text-[11.5px] leading-relaxed text-muted">
                Así se verá sobre el fondo del juego.
            </p>

            <div class="previsualizacion mt-4">
                <span class="placa-logo placa-lg" data-placa-previa
                      @if (empty($marca['logo'])) style="display:none" @endif>
                    <img src="{{ $marca['logo'] ?? '' }}" alt="{{ $marca['nombre'] }}" data-img-previa>
                </span>

                <p class="text-[11px] text-muted" data-sin-logo
                   @if (! empty($marca['logo'])) style="display:none" @endif>
                    Sin logo cargado
                </p>
            </div>

            <dl class="mt-4 space-y-2 text-[11.5px]">
                <div class="flex justify-between gap-3">
                    <dt class="text-muted">Origen</dt>
                    <dd class="text-right font-semibold text-ink-soft">
                        {{ $empresa ? 'Dashboard' : 'Valor por defecto' }}
                    </dd>
                </div>
                @if ($empresa?->updated_at)
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted">Actualizado</dt>
                        <dd class="text-right font-mono text-muted-2">
                            {{ $empresa->updated_at->format('d/m/Y H:i') }}
                        </dd>
                    </div>
                @endif
            </dl>

            <p class="eyebrow mt-5 mb-2">Dónde se aplica</p>
            <ul class="space-y-1.5">
                @foreach ([['login', 'Acceso al juego'], ['ranking', 'Ranking general'], ['certificado', 'Certificado en PDF']] as [$ruta, $etiqueta])
                    <li class="flex items-center gap-2 text-[11.5px] text-muted">
                        <span class="text-ok">@include('partials.icono', ['n' => 'check', 'c' => 'w-3.5 h-3.5'])</span>
                        <a href="{{ route($ruta) }}" target="_blank" rel="noopener" class="hover:text-cyan">{{ $etiqueta }}</a>
                    </li>
                @endforeach
            </ul>
        </aside>
    </form>

    {{-- Quitar logo --}}
    @if ($empresa?->logo_ruta)
        <form method="POST" action="{{ route('dashboard.empresa.logo.quitar') }}" class="anim-in mt-4" style="--i:4">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-[11.5px] font-semibold text-muted hover:text-pink-soft">
                Quitar el logo y volver al que trae el juego
            </button>
        </form>
    @endif
</div>
@endsection
