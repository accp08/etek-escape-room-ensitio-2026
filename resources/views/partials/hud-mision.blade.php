{{-- Cabecera de misión. El cronómetro arranca con los segundos que informa
     el servidor, así recargar la página no regala tiempo. --}}
<header class="safe-top shrink-0 border-b border-[rgba(46,91,140,.4)] bg-[rgba(5,12,26,.82)] backdrop-blur-md">
    <div class="px-4 pt-3 pb-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('hub') }}" class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-[rgba(46,91,140,.5)] text-muted hover:text-cyan" aria-label="Volver al centro de mando">
                @include('partials.icono', ['n' => 'back', 'c' => 'w-4 h-4'])
            </a>

            <div class="min-w-0 flex-1">
                @if ($mision->etiqueta)
                    <p class="eyebrow">{{ $mision->etiqueta }}</p>
                @endif
                <h1 class="truncate text-[15px] font-extrabold text-ink-soft">{{ $mision->titulo }}</h1>
            </div>

            <button type="button" data-abrir-instrucciones
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-[rgba(0,229,255,.35)] bg-[rgba(0,229,255,.08)] text-cyan hover:bg-[rgba(0,229,255,.16)]"
                    aria-label="{{ txt('mision.ver_instrucciones', 'Ver instrucciones') }}" title="{{ txt('mision.ver_instrucciones', 'Ver instrucciones') }}">
                @include('partials.icono', ['n' => 'info', 'c' => 'w-4 h-4'])
            </button>

            <div class="shrink-0 text-right">
                <p class="timer text-2xl" data-timer
                   data-seconds="{{ $avance->segundosRestantes() }}"
                   data-total="{{ $mision->duracion }}">--:--</p>
                <p class="eyebrow leading-none">restante</p>
            </div>
        </div>

        <div class="bar mt-3" data-timer-bar><span style="width:100%"></span></div>

        <div class="mt-2 flex items-center justify-between text-[11px] text-muted">
            <span class="inline-flex items-center gap-1.5">
                @include('partials.icono', ['n' => 'shield', 'c' => 'w-3.5 h-3.5'])
                Intento <strong class="text-ink-soft">{{ $avance->intentos_usados + 1 }}</strong>
                {{ $mision->intentosIlimitados() ? '· sin límite' : 'de ' . $mision->intentos }}
            </span>

            @if ($avance->reactivada)
                <span class="badge badge-pink">Segunda oportunidad</span>
            @else
                <span class="badge badge-muted">Reto en sitio</span>
            @endif
        </div>
    </div>
</header>
