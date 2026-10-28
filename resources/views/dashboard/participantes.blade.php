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
            <span class="badge">{{ number_format($total, 0, ',', '.') }} registrados</span>
        </div>

        <p class="mt-2 text-[12.5px] leading-relaxed text-muted">
            Cada persona entra al juego solo con su <strong class="text-ink-soft">correo</strong>.
            @if ($total > 0)
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

        @if ($total > 0)
            {{-- Buscador y filtros: la lista es larga (hasta miles de personas). --}}
            <form method="GET" class="panel-flat mt-5 flex flex-wrap items-end gap-3 p-4">
                <div class="min-w-[200px] flex-[2]">
                    <label class="field-label">Buscar</label>
                    <input name="q" type="search" class="field" value="{{ $busqueda }}" placeholder="Nombre, correo o área" maxlength="80">
                </div>

                @if ($areas->isNotEmpty())
                    <div class="min-w-[140px] flex-1">
                        <label class="field-label">Área</label>
                        <select name="area" class="field">
                            <option value="">Todas</option>
                            @foreach ($areas as $a)
                                <option value="{{ $a }}" @selected($area === $a)>{{ $a }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @foreach ([
                    'estado' => ['Acceso', 'Todos'],
                    'ranking' => ['Ranking', 'Todos'],
                    'partida' => ['Partida', 'Todas'],
                ] as $clave => [$etiqueta, $todos])
                    <div class="min-w-[130px] flex-1">
                        <label class="field-label">{{ $etiqueta }}</label>
                        <select name="{{ $clave }}" class="field">
                            <option value="">{{ $todos }}</option>
                            @foreach (\App\Http\Controllers\Dashboard\ParticipanteController::FILTROS[$clave] as $v => $et)
                                <option value="{{ $v }}" @selected($filtros[$clave] === $v)>{{ $et }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach

                <div class="flex items-center gap-3">
                    <button type="submit" class="btn btn-primary !w-auto !px-4 !py-2.5 !text-[12px]">Filtrar</button>
                    @if ($filtrando)
                        <a href="{{ route('dashboard.participantes') }}" class="text-[11.5px] font-semibold text-muted hover:text-cyan">Limpiar</a>
                    @endif
                </div>
            </form>

            <p class="mt-3 text-[11.5px] text-muted">
                @if ($participantes->total() === 0)
                    Ningún participante coincide con la búsqueda.
                @else
                    Mostrando {{ $participantes->firstItem() }}–{{ $participantes->lastItem() }}
                    de {{ number_format($participantes->total(), 0, ',', '.') }}{{ $filtrando ? ' encontrados' : '' }}.
                @endif
            </p>
        @else
            <div class="panel-flat mt-5 p-6 text-center">
                <p class="text-[13px] text-muted">Todavía no hay participantes. Crea el primero a la derecha o carga el Excel.</p>
            </div>
        @endif

        <div class="mt-5 space-y-3">
            @foreach ($participantes as $p)
                @php $partida = $p->ultimaPartida; @endphp

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

        @if ($participantes->hasPages())
            <div class="mt-4">{{ $participantes->links() }}</div>
        @endif
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
                Sube un Excel (.xlsx) o CSV con las columnas <strong class="text-ink-soft">Nombre, Área, Correo,
                Activo y En ranking</strong>. Nombre y correo son obligatorios; Activo y En ranking van con
                <strong class="text-ink-soft">Sí</strong> o <strong class="text-ink-soft">No</strong>
                (vacías quedan en Sí). Hasta {{ number_format(\App\Support\ImportadorParticipantes::MAXIMO_FILAS, 0, ',', '.') }} filas.
            </p>
            <a href="{{ route('dashboard.participantes.plantilla') }}"
               class="mt-2 inline-flex items-center gap-1.5 text-[11.5px] font-semibold text-cyan hover:underline">
                @include('partials.icono', ['n' => 'download', 'c' => 'w-3.5 h-3.5'])
                Descargar plantilla (Excel)
            </a>

            <form method="POST" action="{{ route('dashboard.participantes.importar') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <input type="file" name="archivo" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain" required
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
