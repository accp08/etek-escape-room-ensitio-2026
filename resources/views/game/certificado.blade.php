{{-- Certificado de participación. Se exporta a PDF desde el navegador
     (Imprimir → Guardar como PDF), en A4 horizontal y con la gráfica del juego. --}}
@extends('layouts.app')
@section('titulo', 'Certificado de participación')

@php
    $jugables  = $tablero->filter(fn ($f) => $f['mision']->jugable);
    $superadas = $jugables->where('estado', 'superada');
    $fecha     = ($partida->finalizada_en ?? now())->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    $folio     = 'CI-' . now()->format('Y') . '-' . str_pad((string) $partida->id, 4, '0', STR_PAD_LEFT);

    // El texto del certificado se edita en el dashboard (Textos → Certificado).
    // Se escapa primero y luego se insertan los comodines ya resaltados.
    $completo = $superadas->count() === $jugables->count();
    $textoCertificado = strtr(e($completo
        ? txt('certificado.texto_completo', 'Por superar {misiones} del escape room de ciberseguridad, analizar las evidencias del incidente y recuperar el código {codigo} que permitió revertir el ataque antes de que se materializara.')
        : txt('certificado.texto_parcial', 'Por superar {misiones} del escape room de ciberseguridad, analizar las evidencias del incidente y responder ante la amenaza bajo presión.')
    ), [
        '{misiones}' => '<strong>' . e($superadas->count() . ' de ' . $jugables->count() . ' misiones') . '</strong>',
        '{codigo}'   => '<strong>' . e($codigo) . '</strong>',
        '{nombre}'   => e($participante->nombre),
    ]);
@endphp

@section('contenido')
<div class="cert-pagina safe-top px-5 pb-8 pt-6">

    <div class="solo-pantalla">
        <a href="{{ route('victoria') }}" class="mb-5 inline-flex items-center gap-2 text-xs font-semibold text-muted hover:text-cyan">
            @include('partials.icono', ['n' => 'back', 'c' => 'w-4 h-4'])
            Volver
        </a>

        <p class="eyebrow anim-in">Reconocimiento</p>
        <h1 class="anim-in mt-1 text-[24px] font-black leading-tight text-ink" style="--i:1">
            {{ txt('certificado.pantalla_titulo', 'Tu certificado está listo') }}
        </h1>
        <p class="anim-in mt-2.5 text-[12.5px] leading-relaxed text-muted" style="--i:2">
            {{ txt('certificado.pantalla_texto', 'Descárgalo en PDF y consérvalo como constancia de tu participación en el protocolo de contención.') }}
        </p>

        <p class="eyebrow mt-6 mb-2.5">Vista previa</p>
    </div>

    {{-- El certificado: misma marcación en pantalla y en la hoja impresa --}}
    <div class="cert-marco anim-in" style="--i:3">
        <article class="cert">
            <span class="cert-esquina ar-iz"></span>
            <span class="cert-esquina ar-de"></span>
            <span class="cert-esquina ab-iz"></span>
            <span class="cert-esquina ab-de"></span>

            <header class="cert-encabezado">
                <div>
                    <p class="cert-eyebrow">{{ txt('certificado.encabezado', 'Certificado de participación') }}</p>
                    <p class="cert-marca">{{ ajuste('juego_titulo', 'Ciberamenaza Inminente') }}</p>
                    <p class="cert-eyebrow" style="letter-spacing:.22em; margin-top:.5cqw">
                        {{ ajuste('juego_subtitulo', '') }}
                    </p>
                </div>

                @include('partials.logo', ['tam' => 'cert'])
            </header>

            <div class="cert-cuerpo">
                <p class="cert-otorga">{{ txt('certificado.otorga', 'Se otorga el presente reconocimiento a') }}</p>

                <p class="cert-nombre">{{ $participante->nombre }}</p>
                <div class="cert-subrayado"></div>

                <p class="cert-texto">{!! nl2br($textoCertificado, false) !!}</p>

                <div class="cert-datos">
                    <div class="cert-dato">
                        <p class="cert-dato-valor">{{ $superadas->count() }}/{{ $jugables->count() }}</p>
                        <p class="cert-dato-etiqueta">Misiones</p>
                    </div>
                    <div class="cert-dato">
                        <p class="cert-dato-valor">{{ $partida->aciertos }}/{{ $partida->aciertos_posibles }}</p>
                        <p class="cert-dato-etiqueta">Aciertos</p>
                    </div>
                    <div class="cert-dato">
                        <p class="cert-dato-valor">{{ $partida->tiempoLegible() }}</p>
                        <p class="cert-dato-etiqueta">Tiempo</p>
                    </div>
                    <div class="cert-dato">
                        <p class="cert-dato-valor">{{ $puesto ? '#' . $puesto : '—' }}</p>
                        <p class="cert-dato-etiqueta">Puesto</p>
                    </div>
                </div>
            </div>

            <footer class="cert-pie">
                <div class="cert-folio" style="text-align:left">
                    {{ $participante->area }}<br>
                    {{ $fecha }}
                </div>

                <div class="cert-firma">
                    <div class="cert-sello">
                        @include('partials.icono', ['n' => 'shield', 'c' => ''])
                    </div>
                    <div class="cert-firma-linea"></div>
                    <p class="cert-firma-nombre">{{ txt('certificado.firma', 'Comité de Seguridad de la Información') }}</p>
                    <p class="cert-firma-cargo">{{ $marca['nombre'] }}</p>
                </div>

                <div class="cert-folio">
                    Folio <strong>{{ $folio }}</strong><br>
                    Documento <strong>{{ $participante->documento }}</strong>
                </div>
            </footer>
        </article>
    </div>

    <div class="solo-pantalla mt-6 space-y-3">
        <button type="button" class="btn btn-primary"
                data-descargar-certificado
                data-aviso="{{ route('certificado.descargado') }}"
                data-token="{{ csrf_token() }}">
            @include('partials.icono', ['n' => 'download', 'c' => 'w-4 h-4'])
            {{ txt('certificado.descargar', 'Descargar certificado en PDF') }}
        </button>

        <p class="text-center text-[11px] leading-relaxed text-muted">
            Se abrirá el diálogo de impresión. Elige <strong class="text-ink-soft">Guardar como PDF</strong>
            y activa <strong class="text-ink-soft">Gráficos de fondo</strong> para conservar el diseño.
        </p>

        @if (ajuste('video_final'))
            <button type="button" data-abrir-video class="btn btn-ghost">
                @include('partials.icono', ['n' => 'play', 'c' => 'w-4 h-4'])
                {{ txt('certificado.video', 'Ver el video de la experiencia') }}
            </button>
        @endif

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

@section('hojas')
    @if (ajuste('video_final'))
        {{-- Reproductor a pantalla completa dentro del marco del celular.
             El archivo no se descarga hasta que se abre (preload="none" y el
             src se asigna al abrir): son ~21 MB. --}}
        <div class="hoja solo-pantalla fixed inset-0 z-50 hidden" data-hoja-video>
            <div class="absolute inset-0 bg-[rgba(3,7,16,.92)] backdrop-blur-sm" data-cerrar></div>

            <div class="absolute inset-0 flex flex-col bg-[color:var(--color-bg-deep)]" data-panel>

                <div class="safe-top flex shrink-0 items-center gap-3 border-b border-[rgba(46,91,140,.4)] bg-[rgba(5,12,26,.9)] px-4 py-3">
                    <button type="button" data-cerrar
                            class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-[rgba(46,91,140,.5)] text-muted hover:text-cyan"
                            aria-label="Cerrar el video">
                        @include('partials.icono', ['n' => 'x', 'c' => 'w-4 h-4'])
                    </button>
                    <div class="min-w-0 flex-1">
                        <p class="eyebrow">Ciberamenaza Inminente</p>
                        <p class="truncate text-[12.5px] font-bold text-ink">La experiencia</p>
                    </div>
                </div>

                {{-- El video es apaisado y el marco es vertical: se enmarca centrado
                     sobre el fondo del juego en lugar de dejar bandas negras sueltas. --}}
                <div class="flex flex-1 flex-col items-center justify-center gap-5 px-4">
                    <div class="w-full overflow-hidden rounded-2xl border border-[rgba(0,229,255,.35)] bg-black shadow-[0_0_46px_rgba(0,229,255,.16)]">
                        <video data-video class="block h-auto w-full"
                               controls playsinline preload="none"
                               data-fuente="{{ route('video.final') }}">
                            Tu navegador no puede reproducir este video.
                        </video>
                    </div>

                    <p class="max-w-[30ch] text-center text-[11px] leading-relaxed text-muted">
                        Gira el teléfono o usa el botón de pantalla completa del reproductor
                        para verlo más grande.
                    </p>
                </div>

                <div class="safe-bottom shrink-0 border-t border-[rgba(46,91,140,.4)] bg-[rgba(5,12,26,.92)] px-4 pt-3">
                    <button type="button" data-cerrar class="btn btn-ghost !py-3 !text-[13px]">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
@endsection
