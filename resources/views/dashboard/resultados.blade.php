@extends('layouts.dashboard')
@section('seccion', 'Reportes')
@section('titulo', 'Resultados')

@section('contenido')
<div class="panel p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="eyebrow">Desempeño</p>
            <h2 class="mt-1 text-[18px] font-black text-ink">Resultados y ranking</h2>
        </div>
        <a href="{{ route('dashboard.resultados.csv') }}" class="btn btn-ghost !w-auto !px-4 !py-2 !text-[12px]">
            @include('partials.icono', ['n' => 'download', 'c' => 'w-3.5 h-3.5'])
            Exportar CSV
        </a>
    </div>

    {{-- Seguimiento del certificado --}}
    <div class="mt-5 grid gap-3 sm:grid-cols-4">
        @foreach ([
            ['descargado', 'Descargaron', $certificados['descargado'], '#17a55c'],
            ['visto',      'Solo lo vieron', $certificados['visto'],   '#009fb8'],
            ['pendiente',  'No lo abrieron', $certificados['pendiente'], '#ff2da1'],
        ] as [$clave, $etiqueta, $valor, $tono])
            <a href="{{ route('dashboard.resultados', $filtro === $clave ? [] : ['certificado' => $clave]) }}"
               class="kpi block transition hover:brightness-110"
               style="--tono: {{ $tono }}; {{ $filtro === $clave ? 'outline: 2px solid ' . $tono . '; outline-offset: 2px;' : '' }}">
                <p class="kpi-valor">{{ $valor }}</p>
                <p class="kpi-etiqueta">{{ $etiqueta }}</p>
                <p class="kpi-nota">
                    {{ $certificados['total'] ? round($valor * 100 / $certificados['total']) : 0 }}% de las partidas
                </p>
            </a>
        @endforeach

        <div class="kpi" style="--tono: #6a5cff">
            <p class="kpi-valor">{{ $certificados['total'] }}</p>
            <p class="kpi-etiqueta">Partidas</p>
            <p class="kpi-nota">
                @if ($filtro)
                    <a href="{{ route('dashboard.resultados') }}" class="font-semibold text-cyan">Quitar filtro</a>
                @else
                    Toca una tarjeta para filtrar
                @endif
            </p>
        </div>
    </div>

    <p class="mt-3 text-[11px] leading-relaxed text-muted">
        «Descargaron» registra que el participante pulsó el botón de descarga. La
        exportación a PDF la hace el navegador, así que si canceló el diálogo de
        impresión el servidor no puede saberlo.
    </p>

    @if ($ranking->isEmpty())
        <div class="panel-flat mt-5 p-6 text-center">
            <p class="text-[13px] text-muted">
                {{ $filtro ? 'Ninguna partida coincide con este filtro.' : 'Todavía no hay partidas con resultados.' }}
            </p>
        </div>
    @else
        <div class="tabla-scroll mt-5 overflow-x-auto rounded-xl border border-[rgba(46,91,140,.4)]">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="bg-[rgba(5,12,26,.7)]">
                        @foreach (['#', 'Participante', 'Correo', 'Área', 'Aciertos', 'Tiempo', 'Estado', 'Certificado'] as $th)
                            <th class="whitespace-nowrap px-3 py-2.5 text-[10px] font-bold uppercase tracking-wider text-muted">{{ $th }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ranking as $f)
                        <tr class="border-t border-[rgba(46,91,140,.28)]">
                            <td class="px-3 py-2.5 font-mono text-[12px] font-black text-cyan">{{ $f['puesto'] }}</td>
                            <td class="px-3 py-2.5 text-[12.5px] font-bold text-ink">{{ $f['nombre'] }}</td>
                            <td class="px-3 py-2.5 text-[11.5px] text-muted">{{ $f['partida']->participante->correo }}</td>
                            <td class="px-3 py-2.5 text-[11.5px] text-muted">{{ $f['area'] }}</td>
                            <td class="px-3 py-2.5 font-mono text-[12px] text-ink-soft">{{ $f['aciertos'] }}/{{ $f['total'] }}</td>
                            <td class="px-3 py-2.5 font-mono text-[12px] text-muted-2">{{ $f['tiempo'] }}</td>
                            <td class="px-3 py-2.5">
                                <span class="badge {{ $f['partida']->completada() ? 'badge-ok' : 'badge-muted' }}">
                                    {{ $f['partida']->completada() ? 'Completada' : 'En curso' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5">
                                @php $cert = $f['partida']->estadoCertificado(); @endphp

                                @if ($cert === 'descargado')
                                    <span class="badge badge-ok">Descargado</span>
                                    <span class="ml-1 font-mono text-[10px] text-muted">
                                        {{ $f['partida']->certificado_descargado_en->format('d/m H:i') }}
                                        @if ($f['partida']->certificado_descargas > 1)
                                            · {{ $f['partida']->certificado_descargas }}×
                                        @endif
                                    </span>
                                @elseif ($cert === 'visto')
                                    <span class="badge">Solo lo vio</span>
                                @else
                                    <span class="badge badge-pink">No lo abrió</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Detalle por misión --}}
@if ($partidas->isNotEmpty())
    <section class="panel mt-4 p-5">
        <p class="eyebrow">Detalle por misión</p>

        <div class="mt-4 space-y-3">
            @foreach ($partidas as $partida)
                <div class="panel-flat p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-[13px] font-bold text-ink">{{ $partida->participante->nombre }}</p>
                        <span class="font-mono text-[11px] text-muted">
                            {{ $partida->aciertos }}/{{ $partida->aciertos_posibles }} · {{ $partida->tiempoLegible() }}
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($partida->misiones as $avance)
                            @php
                                $tono = match (true) {
                                    $avance->superada() => 'badge-ok',
                                    $avance->fallada() => 'badge-pink',
                                    default => 'badge-muted',
                                };
                            @endphp
                            <span class="badge {{ $tono }} !text-[9.5px]">
                                {{ $avance->mision->etiqueta ?: $avance->mision->titulo }}: {{ $avance->aciertos }}/{{ $avance->aciertos_posibles }}
                                @if ($avance->reactivada) · 2ª @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
@endsection
