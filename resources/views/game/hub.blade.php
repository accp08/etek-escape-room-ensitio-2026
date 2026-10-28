@extends('layouts.app')
@section('titulo', 'Centro de mando')

@php
    $totalJugables = $jugables->count();
    $superadas     = $jugables->where('estado', 'superada')->count();
    $codigoCompleto = $totalJugables > 0 && $superadas === $totalJugables;
@endphp

@section('contenido')
<div class="safe-top px-5 pb-8 pt-6">

    <div class="anim-in flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="eyebrow">{{ txt('hub.titulo', 'Centro de mando') }}</p>
            <h1 class="mt-1 truncate text-[22px] font-black leading-tight text-ink">
                {{ txt('hub.saludo', 'Hola') }}, {{ \Illuminate\Support\Str::of($participante->nombre)->explode(' ')->first() }}
            </h1>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            @if ($audio)
                <audio src="{{ $audio }}" loop preload="auto" data-proy-audio></audio>
                <button type="button" data-proy-sonido
                        class="grid h-10 w-10 place-items-center rounded-xl border border-[rgba(46,91,140,.5)] text-muted hover:text-cyan"
                        aria-label="Sonido" title="Sonido">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M11 5 6 9H3v6h3l5 4V5z"/>
                        <path data-sonido-on d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/>
                        <path data-sonido-off d="m16 9 6 6M22 9l-6 6"/>
                    </svg>
                </button>
            @endif
            <button type="button" data-abrir-hoja="instrucciones-juego"
                    class="grid h-10 w-10 place-items-center rounded-xl border border-[rgba(46,91,140,.5)] text-muted hover:text-cyan"
                    aria-label="{{ txt('hub.instrucciones', 'Instrucciones del juego') }}" title="{{ txt('hub.instrucciones', 'Instrucciones del juego') }}">
                @include('partials.icono', ['n' => 'info', 'c' => 'w-5 h-5'])
            </button>
            <a href="{{ route('ranking') }}"
               class="grid h-10 w-10 place-items-center rounded-xl border border-[rgba(46,91,140,.5)] text-muted hover:text-cyan"
               aria-label="Ver ranking">
                @include('partials.icono', ['n' => 'trophy', 'c' => 'w-5 h-5'])
            </a>
        </div>
    </div>

    {{-- Estado del ataque --}}
    <div class="panel scan anim-in mt-5 overflow-hidden p-5" style="--i:1">
        <div class="flex items-center justify-between">
            <span class="badge badge-pink">
                @include('partials.icono', ['n' => 'alert', 'c' => 'w-3.5 h-3.5'])
                {{ txt('hub.estado_ataque', 'Ataque en curso') }}
            </span>
            <span class="font-mono text-sm font-bold text-muted-2">
                {{ $superadas }}/{{ $totalJugables }}
            </span>
        </div>

        <p class="mt-4 text-[13px] leading-relaxed text-muted">
            {{ txt('hub.descripcion', 'Recupera los fragmentos del código de recuperación.') }}
        </p>

        {{-- Fragmentos --}}
        <div class="mt-4 grid gap-2" style="grid-template-columns: repeat({{ max(1, $totalJugables) }}, minmax(0,1fr))">
            @foreach ($jugables as $fila)
                <div class="code-slot text-lg" data-filled="{{ $fila['estado'] === 'superada' ? 'true' : 'false' }}">
                    {{ $fila['estado'] === 'superada' ? $fila['fragmento'] : str_repeat('·', \App\Models\CodigoMision::largo()) }}
                </div>
            @endforeach
        </div>

        <div class="bar bar-anim mt-4">
            <span style="width: {{ $totalJugables ? ($superadas / $totalJugables) * 100 : 0 }}%"></span>
        </div>
        <p class="mt-2 text-[11px] text-muted">{{ $superadas }} de {{ $totalJugables }} misiones superadas</p>
    </div>

    {{-- Con todos los fragmentos, el código completo pasa a ser lo primero. --}}
    @if ($codigoCompleto)
        <a href="{{ route('final') }}" class="btn btn-danger anim-in mt-6" style="--i:2">
            @include('partials.icono', ['n' => 'shield', 'c' => 'w-4 h-4'])
            {{ txt('hub.boton_codigo', 'Ingresar código de recuperación') }}
        </a>
    @endif

    <p class="eyebrow anim-in mt-7" style="--i:2">{{ txt('hub.misiones', 'Misiones disponibles') }}</p>

    <div class="mt-3 space-y-3">
        @foreach ($tablero as $i => $fila)
            @php
                $mision = $fila['mision'];
                $avance = $fila['avance'];
                $estado = $fila['estado'];

                $clase = match ($estado) {
                    'superada'   => 'node-done',
                    'disponible' => 'node-active',
                    'fallada'    => 'node-failed',
                    default      => 'node-locked',
                };

                $abierta = $estado === 'disponible';
            @endphp

            <a href="{{ $abierta ? route('mision', $mision->slug) : '#' }}"
               @class(['panel-flat anim-in block p-4', $clase]) style="--i:{{ $i + 3 }}"
               @unless ($abierta) aria-disabled="true" tabindex="-1" @endunless>

                <div class="flex items-center gap-3.5">
                    <div @class([
                        'grid h-11 w-11 shrink-0 place-items-center rounded-xl border',
                        'border-[rgba(34,197,94,.45)] bg-[rgba(34,197,94,.12)] text-ok' => $estado === 'superada',
                        'border-[rgba(0,229,255,.4)] bg-[rgba(0,229,255,.1)] text-cyan' => $estado === 'disponible',
                        'border-[rgba(255,45,161,.4)] bg-[rgba(255,45,161,.08)] text-pink-soft' => $estado === 'fallada',
                        'border-[rgba(46,91,140,.5)] text-muted' => $estado === 'bloqueada',
                    ])>
                        @if ($estado === 'superada')
                            @include('partials.icono', ['n' => 'check', 'c' => 'w-5 h-5'])
                        @elseif ($estado === 'bloqueada')
                            @include('partials.icono', ['n' => 'lock', 'c' => 'w-5 h-5'])
                        @elseif ($estado === 'fallada')
                            @include('partials.icono', ['n' => 'x', 'c' => 'w-5 h-5'])
                        @else
                            @include('partials.icono', ['n' => $mision->icono, 'c' => 'w-5 h-5'])
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="eyebrow">Misión {{ $i + 1 }}</span>
                            @if ($estado === 'superada')
                                <span class="code-chip !px-2 !py-0.5 text-[10px] !tracking-[2px]">{{ $fila['fragmento'] }}</span>
                            @endif
                            @if ($avance?->reactivada && $abierta)
                                <span class="badge badge-pink !py-0 !text-[9px]">2ª oportunidad</span>
                            @endif
                        </div>
                        <h2 class="mt-0.5 text-[15px] font-extrabold leading-tight text-ink">{{ $mision->titulo }}</h2>
                        @if ($mision->etiqueta)
                            <p class="mt-0.5 truncate text-[11px] text-muted">{{ $mision->etiqueta }}</p>
                        @endif
                    </div>

                    <div class="shrink-0 text-right">
                        @if ($estado === 'superada')
                            <span class="badge badge-ok">{{ $avance->aciertos }}/{{ $avance->aciertos_posibles }}</span>
                        @elseif ($estado === 'disponible')
                            <span class="badge">Abierta</span>
                        @elseif ($estado === 'fallada')
                            <span class="badge badge-pink">Fallada</span>
                        @else
                            <span class="badge badge-muted">{{ txt('hub.proximamente', 'Próximamente') }}</span>
                        @endif

                        <p class="mt-1.5 inline-flex items-center gap-1 text-[10px] text-muted">
                            @include('partials.icono', ['n' => 'clock', 'c' => 'w-3 h-3'])
                            {{ intdiv($mision->duracion, 60) }} min
                        </p>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    @unless ($codigoCompleto)
        <a href="{{ route('final') }}" class="btn btn-danger anim-in mt-6" style="--i:8">
            @include('partials.icono', ['n' => 'shield', 'c' => 'w-4 h-4'])
            {{ txt('hub.boton_codigo', 'Ingresar código de recuperación') }}
        </a>
    @endunless

    <form method="POST" action="{{ route('salir') }}" class="mt-3 text-center">
        @csrf
        <button type="submit" class="text-[11px] font-semibold text-muted hover:text-cyan">Cerrar sesión</button>
    </form>
</div>
@endsection

@section('hojas')
    {{-- Instrucciones del juego: los mismos pasos del briefing inicial
         (se administran en Dashboard → Instrucciones). --}}
    <div class="hoja fixed inset-0 z-50 hidden" data-hoja="instrucciones-juego">
        <div class="absolute inset-0 bg-[rgba(3,7,16,.82)] backdrop-blur-sm" data-cerrar></div>

        <div class="absolute inset-x-0 bottom-0 max-h-[85%] overflow-y-auto rounded-t-[22px] border-t border-[rgba(0,229,255,.35)] bg-[color:var(--color-bg-1)] px-5 pb-6 pt-4" data-panel>
            <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-[rgba(46,91,140,.8)]"></div>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="eyebrow">{{ txt('briefing.titulo', 'Briefing de misión') }}</p>
                    <h2 class="mt-1 text-[18px] font-black text-ink">{{ txt('hub.instrucciones', 'Instrucciones del juego') }}</h2>
                </div>
                <button type="button" data-cerrar class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-[rgba(46,91,140,.5)] text-muted hover:text-cyan" aria-label="Cerrar">
                    @include('partials.icono', ['n' => 'x', 'c' => 'w-4 h-4'])
                </button>
            </div>

            <ol class="mt-4 space-y-2.5">
                @forelse ($pasos as $paso)
                    <li class="panel-flat flex items-start gap-3 p-3.5">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-[rgba(0,229,255,.35)] bg-[rgba(0,229,255,.1)] text-cyan">
                            @include('partials.icono', ['n' => $paso->icono, 'c' => 'w-5 h-5'])
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-mono text-[10px] text-muted">Paso {{ $loop->iteration }} de {{ $loop->count }}</p>
                            <p class="mt-0.5 text-[13.5px] font-bold leading-snug text-ink">{{ $paso->titulo }}</p>
                            <p class="mt-1 text-[12px] leading-relaxed text-muted">{{ $paso->texto }}</p>
                        </div>
                    </li>
                @empty
                    <li class="text-[12px] text-muted">No hay instrucciones configuradas.</li>
                @endforelse
            </ol>

            <button type="button" data-cerrar class="btn btn-primary mt-5">Entendido</button>
        </div>
    </div>
@endsection
