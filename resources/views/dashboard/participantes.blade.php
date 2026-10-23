@extends('layouts.dashboard')
@section('seccion', 'Usuarios')
@section('titulo', 'Participantes')

@section('contenido')
<div class="grid gap-4 md:grid-cols-[1fr_340px]">

    <section class="panel p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="eyebrow">Quiénes juegan</p>
                <h2 class="mt-1 text-[18px] font-black text-ink">Participantes</h2>
            </div>
            <span class="badge">{{ $participantes->count() }} registrados</span>
        </div>

        <p class="mt-2 text-[12.5px] leading-relaxed text-muted">
            Cada persona entra al juego solo con su <strong class="text-ink-soft">correo</strong>.
            @if ($participantes->isNotEmpty())
                <a href="{{ route('dashboard.participantes.csv') }}" class="font-semibold text-cyan hover:underline">Descargar todos (CSV)</a>
            @endif
        </p>

        @if ($reporte = session('importacion'))
            <div @class(['aviso mt-4', $reporte['errores'] ? 'aviso-error' : 'aviso-ok'])>
                @include('partials.icono', ['n' => $reporte['errores'] ? 'alert' : 'check', 'c' => 'w-4 h-4 shrink-0 mt-px'])
                <div class="min-w-0 flex-1">
                    <p>
                        <strong>Carga masiva:</strong>
                        {{ $reporte['creados'] }} creados ·
                        {{ $reporte['actualizados'] }} actualizados ·
                        {{ $reporte['omitidos'] }} ya existían (sin cambios) ·
                        {{ count($reporte['errores']) }} con error.
                    </p>
                    @if ($reporte['errores'])
                        <ul class="mt-2 max-h-48 space-y-0.5 overflow-y-auto text-[11.5px]">
                            @foreach ($reporte['errores'] as $linea => $error)
                                <li>{{ $linea ? "Fila {$linea}: " : '' }}{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif

        @if ($participantes->isEmpty())
            <div class="panel-flat mt-5 p-6 text-center">
                <p class="text-[13px] text-muted">Todavía no hay participantes. Crea el primero a la derecha.</p>
            </div>
        @endif

        <div class="mt-5 space-y-3">
            @foreach ($participantes as $p)
                @php $partida = $p->partidas->sortByDesc('id')->first(); @endphp

                <div class="panel-flat p-4">
                    <form method="POST" action="{{ route('dashboard.participantes.actualizar', $p) }}" id="part-{{ $p->id }}">
                        @csrf @method('PUT')
                    </form>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-[12px] text-ink-soft">{{ $p->correo ?: 'Sin correo: no puede entrar' }}</span>
                        @if (! $p->activo)
                            <span class="badge badge-muted">Inactivo</span>
                        @endif
                        @if (! $p->en_ranking)
                            <span class="badge badge-muted">Fuera del ranking</span>
                        @endif
                        @if ($partida)
                            <span class="badge {{ $partida->estado === 'completada' ? 'badge-ok' : '' }}">
                                {{ $partida->estado === 'completada' ? 'Completó' : 'En curso' }}
                                · {{ $partida->aciertos }}/{{ $partida->aciertos_posibles }}
                            </span>
                        @endif
                    </div>

                    {{-- Fragmentos propios del participante, en orden de misión. --}}
                    @php $fragmentos = $p->codigos->pluck('fragmento', 'mision_id'); @endphp
                    <p class="mt-2 font-mono text-[10.5px] text-muted">
                        Fragmentos:
                        @foreach ($misiones as $m)
                            <span title="{{ $m->titulo }}" class="text-ink-soft">{{ $fragmentos->get($m->id, '—') }}</span>@if (! $loop->last) · @endif
                        @endforeach
                    </p>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="field-label">Nombre</label>
                            <input form="part-{{ $p->id }}" name="nombre" class="field" value="{{ $p->nombre }}" maxlength="120" required>
                        </div>
                        <div>
                            <label class="field-label">Área</label>
                            <input form="part-{{ $p->id }}" name="area" class="field" value="{{ $p->area }}" maxlength="80">
                        </div>
                        <div>
                            <label class="field-label">Correo</label>
                            <input form="part-{{ $p->id }}" name="correo" type="email" class="field" value="{{ $p->correo }}" maxlength="160" required>
                        </div>
                    </div>

                    {{-- Los ocultos envían 0 cuando la casilla queda desmarcada. --}}
                    <input form="part-{{ $p->id }}" type="hidden" name="activo" value="0">
                    <input form="part-{{ $p->id }}" type="hidden" name="en_ranking" value="0">

                    <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2">
                        <label class="flex items-center gap-2.5">
                            <input form="part-{{ $p->id }}" type="checkbox" name="activo" value="1" @checked($p->activo)
                                   class="h-4 w-4 rounded border-[rgba(46,91,140,.7)] bg-[rgba(5,12,26,.85)] accent-[color:var(--color-cyan)]">
                            <span class="text-[11.5px] text-muted">Puede entrar al juego</span>
                        </label>
                        <label class="flex items-center gap-2.5">
                            <input form="part-{{ $p->id }}" type="checkbox" name="en_ranking" value="1" @checked($p->en_ranking)
                                   class="h-4 w-4 rounded border-[rgba(46,91,140,.7)] bg-[rgba(5,12,26,.85)] accent-[color:var(--color-cyan)]">
                            <span class="text-[11.5px] text-muted">Aparece en el ranking</span>
                        </label>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button type="submit" form="part-{{ $p->id }}" class="btn btn-primary !w-auto !px-4 !py-2 !text-[12px]">Guardar</button>

                        @if ($partida)
                            <form method="POST" action="{{ route('dashboard.participantes.reiniciar', $p) }}"
                                  onsubmit="return confirm('Se borrará el avance de {{ $p->nombre }}. ¿Continuar?')">
                                @csrf
                                <button type="submit" class="btn btn-ghost !w-auto !px-3.5 !py-2 !text-[12px]">Reiniciar partida</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('dashboard.participantes.borrar', $p) }}"
                              onsubmit="return confirm('Se eliminará a {{ $p->nombre }} y su partida. ¿Continuar?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-[11.5px] font-semibold text-muted hover:text-pink-soft">Eliminar</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <aside class="panel h-fit p-5">
        <p class="eyebrow">Nuevo participante</p>
        <p class="mt-1.5 text-[11.5px] leading-relaxed text-muted">
            Entrará al juego solo con su correo.
        </p>

        <form method="POST" action="{{ route('dashboard.participantes.crear') }}" class="mt-4 space-y-3">
            @csrf

            <div>
                <label class="field-label">Nombre</label>
                <input name="nombre" class="field" maxlength="120" required>
            </div>
            <div>
                <label class="field-label">Área</label>
                <input name="area" class="field" maxlength="80">
            </div>
            <div>
                <label class="field-label">Correo</label>
                <input name="correo" type="email" class="field" maxlength="160" required>
            </div>

            <button type="submit" class="btn btn-primary">Crear participante</button>
        </form>

        <div class="mt-6 border-t border-[rgba(46,91,140,.35)] pt-5">
            <p class="eyebrow">Carga masiva</p>
            <p class="mt-1.5 text-[11.5px] leading-relaxed text-muted">
                Sube un archivo CSV con las columnas <strong class="text-ink-soft">Nombre, Área y Correo</strong>
                (nombre y correo obligatorios). Desde Excel: <em>Archivo → Guardar como → CSV</em>.
            </p>
            <a href="{{ route('dashboard.participantes.plantilla') }}"
               class="mt-2 inline-flex items-center gap-1.5 text-[11.5px] font-semibold text-cyan hover:underline">
                @include('partials.icono', ['n' => 'download', 'c' => 'w-3.5 h-3.5'])
                Descargar plantilla
            </a>

            <form method="POST" action="{{ route('dashboard.participantes.importar') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <input type="file" name="archivo" accept=".csv,text/csv,text/plain" required
                       class="block w-full text-[11.5px] text-muted file:mr-3 file:rounded-lg file:border file:border-[rgba(46,91,140,.6)] file:bg-[rgba(5,12,26,.85)] file:px-3 file:py-2 file:text-[11.5px] file:font-semibold file:text-ink-soft hover:file:text-cyan">

                <label class="flex items-start gap-2.5">
                    <input type="checkbox" name="actualizar" value="1"
                           class="mt-0.5 h-4 w-4 rounded border-[rgba(46,91,140,.7)] bg-[rgba(5,12,26,.85)] accent-[color:var(--color-cyan)]">
                    <span class="text-[11.5px] leading-snug text-muted">
                        Actualizar los que ya existen <span class="text-muted">(se buscan por correo)</span>
                    </span>
                </label>

                <button type="submit" class="btn btn-ghost">Importar participantes</button>
            </form>
        </div>
    </aside>
</div>
@endsection
