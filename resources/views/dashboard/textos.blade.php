@extends('layouts.dashboard')
@section('seccion', 'Contenido')
@section('titulo', 'Textos de interfaz')

@section('contenido')
<form method="POST" action="{{ route('dashboard.textos.guardar') }}" data-textos>
    @csrf @method('PUT')
    <input type="hidden" name="pestana" value="{{ $pestanas->keys()->first() }}" data-pestana-actual>

    <div class="panel p-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">Interfaz del juego</p>
                <h2 class="mt-1 text-[18px] font-black text-ink">Textos de cada pantalla</h2>
                <p class="mt-2 max-w-[60ch] text-[12.5px] leading-relaxed text-muted">
                    Organizados en el orden en que el participante recorre el juego.
                    Elige una etapa y edita los textos de cada pantalla. Los cambios se aplican de inmediato al guardar.
                </p>
            </div>

            <label class="relative w-full sm:w-72">
                <span class="sr-only-c">Buscar un texto</span>
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted">
                    @include('partials.icono', ['n' => 'search', 'c' => 'w-4 h-4'])
                </span>
                <input type="search" class="field !pl-9" placeholder="Buscar en todos los textos…" data-buscar-texto>
            </label>
        </div>

        {{-- Pestañas: una por etapa del recorrido --}}
        <div class="mt-5 flex gap-2 overflow-x-auto border-b border-[rgba(46,91,140,.4)]" role="tablist" data-pestanas>
            @foreach ($pestanas as $clave => $pestana)
                <button type="button" role="tab" class="pestana" data-pestana="{{ $clave }}"
                        aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="panel-{{ $clave }}">
                    <span class="pestana-paso">{{ $loop->iteration }}</span>
                    @include('partials.icono', ['n' => $pestana['icono'], 'c' => 'w-4 h-4'])
                    {{ $pestana['titulo'] }}
                    <span class="pestana-conteo">{{ $pestana['pantallas']->sum(fn ($p) => $p['textos']->count()) }}</span>
                    <span class="pestana-cambios hidden" data-cambios-en="{{ $clave }}" title="Cambios sin guardar"></span>
                </button>
            @endforeach
        </div>
    </div>

    <p class="panel mt-4 hidden p-5 text-center text-[12.5px] text-muted" data-sin-resultados>
        Ningún texto coincide con la búsqueda.
    </p>

    @foreach ($pestanas as $clave => $pestana)
        <div id="panel-{{ $clave }}" role="tabpanel" data-panel-pestana="{{ $clave }}" @class(['hidden' => ! $loop->first])>

            {{-- Índice de pantallas de la etapa --}}
            @if ($pestana['pantallas']->count() > 1)
                <nav class="mt-4 flex flex-wrap gap-2" data-indice>
                    @foreach ($pestana['pantallas'] as $pantalla)
                        <a href="#pantalla-{{ $clave }}-{{ $loop->index }}" class="badge badge-muted !py-1 hover:!text-cyan">
                            {{ $pantalla['titulo'] }} · {{ $pantalla['textos']->count() }}
                        </a>
                    @endforeach
                </nav>
            @endif

            @foreach ($pestana['pantallas'] as $pantalla)
                <section class="panel mt-4 scroll-mt-4 p-5" id="pantalla-{{ $clave }}-{{ $loop->index }}" data-pantalla>
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-[rgba(46,91,140,.3)] pb-4">
                        <div class="flex items-start gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-[rgba(0,229,255,.35)] bg-[rgba(0,229,255,.08)] font-mono text-[12px] font-black text-cyan">
                                {{ $loop->parent->iteration }}.{{ $loop->iteration }}
                            </span>
                            <div>
                                <h3 class="text-[15px] font-extrabold text-ink">{{ $pantalla['titulo'] }}</h3>
                                <p class="mt-0.5 text-[11.5px] leading-relaxed text-muted">{{ $pantalla['ayuda'] }}</p>
                            </div>
                        </div>

                        @if ($pantalla['ruta'])
                            <a href="{{ url($pantalla['ruta']) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 rounded-lg border border-[rgba(46,91,140,.55)] px-2.5 py-1 font-mono text-[10.5px] text-muted hover:text-cyan"
                               title="Abre la pantalla en el juego (algunas requieren haber entrado como participante)">
                                {{ $pantalla['ruta'] }}
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
                            </a>
                        @endif
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        @foreach ($pantalla['textos'] as $texto)
                            <div @class(['md:col-span-2' => $texto->multilinea]) data-campo-texto
                                 data-busqueda="{{ mb_strtolower(($texto->descripcion ?? '') . ' ' . $texto->clave . ' ' . $texto->valor) }}">
                                <label class="field-label" for="texto-{{ $texto->id }}">
                                    {{ $texto->descripcion ?: $texto->clave }}
                                </label>

                                @if ($texto->multilinea)
                                    <textarea id="texto-{{ $texto->id }}" name="textos[{{ $texto->id }}]"
                                              class="field" rows="3" maxlength="2000">{{ $texto->valor }}</textarea>
                                @else
                                    <input id="texto-{{ $texto->id }}" name="textos[{{ $texto->id }}]"
                                           class="field" value="{{ $texto->valor }}" maxlength="2000">
                                @endif

                                <p class="mt-1 font-mono text-[10px] text-muted">{{ $texto->clave }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endforeach

    {{-- Barra de guardado siempre a la vista --}}
    <div class="panel sticky bottom-3 z-10 mt-4 !bg-[rgba(9,17,33,.97)] shadow-[0_-10px_30px_rgba(0,0,0,.45)] backdrop-blur flex flex-wrap items-center justify-between gap-3 p-4">
        <p class="text-[12px] text-muted" data-estado-cambios>Sin cambios pendientes.</p>
        <button type="submit" class="btn btn-primary !w-auto !px-6">Guardar todos los textos</button>
    </div>
</form>
@endsection
