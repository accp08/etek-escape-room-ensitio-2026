@extends('layouts.dashboard')
@section('seccion', 'Reportes')
@section('titulo', 'Bitácora')

@section('contenido')

{{-- Totales --}}
<div class="grid gap-3 sm:grid-cols-4">
    @foreach ([
        ['Registros', $totales['todos'],  '#009fb8'],
        ['Del juego', $totales['juego'],  '#6a5cff'],
        ['Del panel', $totales['panel'],  '#17a55c'],
        ['Alertas',   $totales['alerta'], '#ff2da1'],
    ] as [$etiqueta, $valor, $tono])
        <div class="kpi" style="--tono: {{ $tono }}">
            <p class="kpi-valor">{{ $valor }}</p>
            <p class="kpi-etiqueta">{{ $etiqueta }}</p>
        </div>
    @endforeach
</div>

{{-- Filtros --}}
<form method="GET" class="panel mt-4 flex flex-wrap items-end gap-3 p-4">
    <div class="min-w-[180px] flex-1">
        <label class="field-label">Buscar</label>
        <input name="q" class="field" value="{{ $busqueda }}" placeholder="Descripción o tipo" maxlength="80">
    </div>

    <div class="w-[150px]">
        <label class="field-label">Canal</label>
        <select name="canal" class="field">
            <option value="">Todos</option>
            @foreach (['juego' => 'Juego', 'dashboard' => 'Dashboard', 'sistema' => 'Sistema'] as $v => $et)
                <option value="{{ $v }}" @selected($canal === $v)>{{ $et }}</option>
            @endforeach
        </select>
    </div>

    <div class="w-[150px]">
        <label class="field-label">Nivel</label>
        <select name="nivel" class="field">
            <option value="">Todos</option>
            @foreach (['info' => 'Info', 'aviso' => 'Aviso', 'alerta' => 'Alerta'] as $v => $et)
                <option value="{{ $v }}" @selected($nivel === $v)>{{ $et }}</option>
            @endforeach
        </select>
    </div>

    <button type="submit" class="btn btn-primary !w-auto !px-5 !py-2.5 !text-[12px]">Filtrar</button>

    @if ($busqueda || $canal || $nivel)
        <a href="{{ route('dashboard.registros') }}" class="btn btn-ghost !w-auto !px-4 !py-2.5 !text-[12px]">Limpiar</a>
    @endif
</form>

{{-- Listado --}}
<section class="panel mt-4 p-5">
    @if ($registros->isEmpty())
        <p class="py-8 text-center text-[13px] text-muted">No hay registros que coincidan.</p>
    @else
        <div class="tabla-scroll overflow-x-auto rounded-xl border border-[rgba(46,91,140,.4)]">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="bg-[rgba(5,12,26,.7)]">
                        @foreach (['Cuándo', 'Canal', 'Tipo', 'Descripción', 'Quién', 'IP'] as $th)
                            <th class="whitespace-nowrap px-3 py-2.5 text-[10px] font-bold uppercase tracking-wider text-muted">{{ $th }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($registros as $r)
                        <tr class="border-t border-[rgba(46,91,140,.25)]">
                            <td class="whitespace-nowrap px-3 py-2.5 font-mono text-[11px] text-muted-2">
                                {{ $r->created_at->format('d/m H:i:s') }}
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="inline-flex items-center gap-1.5 text-[11.5px] text-ink-soft">
                                    <span class="registro-punto !mt-0" data-nivel="{{ $r->nivel }}"></span>
                                    {{ $r->canal }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 font-mono text-[10.5px] text-cyan">{{ $r->tipo }}</td>
                            <td class="px-3 py-2.5 text-[12px] text-ink-soft">{{ $r->descripcion }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-[11.5px] text-muted">{{ $r->autor() }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 font-mono text-[10.5px] text-muted">{{ $r->ip }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $registros->links() }}</div>
    @endif
</section>
@endsection
