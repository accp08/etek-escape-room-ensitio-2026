@extends('layouts.dashboard')
@section('seccion', 'Contenido')
@section('titulo', 'Misiones')

@section('contenido')
<div class="panel p-5">
    <p class="eyebrow">Contenido del juego</p>
    <h2 class="mt-1 text-[18px] font-black text-ink">Misiones</h2>
    <p class="mt-2 text-[12.5px] leading-relaxed text-muted">
        Cada misión es una estación de la sala: el participante escanea su QR, resuelve el reto
        y escribe el código. Las marcadas como <strong class="text-ink-soft">jugables</strong> son las que puntúan;
        las demás aparecen en el centro de mando como «Próximamente».
    </p>

    <a href="{{ route('dashboard.misiones.qr') }}" target="_blank" class="btn btn-ghost mt-4 !w-auto">
        @include('partials.icono', ['n' => 'file', 'c' => 'w-4 h-4'])
        Imprimir los QR de las estaciones
    </a>

    <div class="mt-5 space-y-2.5">
        @foreach ($misiones as $mision)
            <div class="panel-flat p-4 transition hover:border-[rgba(0,229,255,.45)]">
                <div class="flex flex-wrap items-center gap-3">
                    <span @class([
                        'grid h-10 w-10 shrink-0 place-items-center rounded-xl border',
                        'border-[rgba(0,229,255,.4)] bg-[rgba(0,229,255,.08)] text-cyan' => $mision->jugable,
                        'border-[rgba(46,91,140,.5)] text-muted' => ! $mision->jugable,
                    ])>
                        @include('partials.icono', ['n' => $mision->icono, 'c' => 'w-5 h-5'])
                    </span>

                    <div class="min-w-0 flex-1">
                        <a href="{{ route('dashboard.misiones.editar', $mision) }}" class="block truncate text-[14px] font-bold text-ink hover:text-cyan">{{ $mision->titulo }}</a>
                        <p class="truncate font-mono text-[10.5px] text-muted">
                            {{ $mision->duracionLegible() }} · {{ $mision->intentosIlimitados() ? 'oportunidades ilimitadas' : $mision->intentos . ' oportunidades' }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @if ($mision->jugable && ! $mision->codigo_presencial)
                            <span class="badge badge-pink">Sin código</span>
                        @endif
                        @if ($mision->jugable)
                            <a href="{{ route('dashboard.misiones.proyectar', $mision) }}" target="_blank" class="btn btn-primary !w-auto !px-3.5 !py-2 text-[12px]">
                                @include('partials.icono', ['n' => 'play', 'c' => 'w-3.5 h-3.5'])
                                Proyectar
                            </a>
                            <span class="badge badge-ok">Jugable</span>
                        @else
                            <span class="badge badge-muted">Bloqueada</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
