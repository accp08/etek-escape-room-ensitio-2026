@extends('layouts.dashboard')
@section('seccion', 'Reportes')
@section('titulo', 'Reporte por áreas')

@section('acciones')
    <a href="{{ route('dashboard.reporte-areas.excel', array_filter(['tematica' => $tematica, 'area' => $area], fn ($valor) => $valor !== '')) }}"
       class="btn btn-ghost !w-auto !px-4 !py-2 !text-[12px]">
        @include('partials.icono', ['n' => 'download', 'c' => 'w-3.5 h-3.5'])
        Descargar Excel
    </a>
@endsection

@section('contenido')
<section class="panel p-5">
    <form method="GET" action="{{ route('dashboard.reporte-areas') }}" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
        <label class="block text-[11px] font-semibold text-muted">
            Temática
            <select name="tematica" class="field mt-1">
                <option value="">Todas las temáticas</option>
                @foreach ($tematicas as $opcion)
                    <option value="{{ $opcion }}" @selected($tematica === $opcion)>{{ $opcion }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-[11px] font-semibold text-muted">
            Área
            <select name="area" class="field mt-1">
                <option value="">Todas las áreas</option>
                @foreach ($areas as $opcion)
                    <option value="{{ $opcion }}" @selected($area === $opcion)>{{ $opcion }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="btn btn-primary !w-auto !px-5 !py-2.5">Aplicar filtros</button>
    </form>
</section>

<div class="mt-4 grid gap-3 sm:grid-cols-2">
    @foreach ([['Área más fuerte', $areaMasFuerte], ['Área más débil', $areaMasDebil]] as [$titulo, $dato])
        <section class="kpi" style="--tono: {{ $loop->first ? '#17a55c' : '#ff2da1' }}">
            <p class="kpi-valor">{{ $dato['area'] ?? 'Sin datos' }}</p>
            <p class="kpi-etiqueta">{{ $titulo }}</p>
            <p class="kpi-nota">{{ isset($dato['precision']) ? $dato['precision'].'% de precisión · '.$dato['intentos'].' intentos' : 'No hay resultados para estos filtros' }}</p>
        </section>
    @endforeach
</div>

<section class="panel mt-4 p-5">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="text-[15px] font-black text-ink">Desempeño por participante y misión</h2>
        <span class="eyebrow">{{ $reporte->count() }} registros</span>
    </div>

    @if ($reporte->isEmpty())
        <p class="mt-6 text-center text-[12.5px] text-muted">No hay intentos registrados para estos filtros.</p>
    @else
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-[12px]">
                <thead class="text-[10px] uppercase tracking-wider text-muted">
                    <tr class="border-b border-[rgba(46,91,140,.3)]">
                        <th class="px-3 py-2">Área</th><th class="px-3 py-2">Participante</th>
                        <th class="px-3 py-2">Temática</th><th class="px-3 py-2">Misión</th>
                        <th class="px-3 py-2 text-right">Intentos</th><th class="px-3 py-2 text-right">Tiempo</th>
                        <th class="px-3 py-2 text-right">Aciertos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reporte as $fila)
                        <tr class="border-b border-[rgba(46,91,140,.16)] text-ink-soft">
                            <td class="px-3 py-3">{{ $fila['area'] }}</td>
                            <td class="px-3 py-3 font-semibold text-ink">{{ $fila['participante'] }}</td>
                            <td class="px-3 py-3">{{ $fila['tematica'] }}</td>
                            <td class="px-3 py-3">{{ $fila['mision'] }}</td>
                            <td class="px-3 py-3 text-right font-mono">{{ $fila['intentos'] }}</td>
                            <td class="px-3 py-3 text-right font-mono">{{ sprintf('%02d:%02d', intdiv($fila['segundos'], 60), $fila['segundos'] % 60) }}</td>
                            <td class="px-3 py-3 text-right font-mono">{{ $fila['aciertos'] }}/{{ $fila['posibles'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
