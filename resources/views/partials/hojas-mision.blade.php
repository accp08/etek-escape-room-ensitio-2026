{{-- Instrucciones de la misión en curso: se pueden consultar en cualquier
     momento desde la cabecera, sin detener el cronómetro. --}}
<div class="hoja fixed inset-0 z-50 hidden" data-instrucciones>
    <div class="absolute inset-0 bg-[rgba(3,7,16,.82)] backdrop-blur-sm" data-cerrar></div>

    <div class="absolute inset-x-0 bottom-0 max-h-[82%] overflow-y-auto rounded-t-[22px] border-t border-[rgba(0,229,255,.35)] bg-[color:var(--color-bg-1)] px-5 pb-6 pt-4" data-panel>
        <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-[rgba(46,91,140,.8)]"></div>
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="eyebrow">{{ $mision->etiqueta ?: txt('hub.instrucciones', 'Instrucciones') }}</p>
                <h2 class="mt-1 text-[18px] font-black leading-tight text-ink">{{ $mision->titulo }}</h2>
            </div>
            <button type="button" data-cerrar class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-[rgba(46,91,140,.5)] text-muted hover:text-cyan" aria-label="Cerrar">
                @include('partials.icono', ['n' => 'x', 'c' => 'w-4 h-4'])
            </button>
        </div>

        @if ($mision->brief)
            <p class="mt-4 text-[12.5px] leading-relaxed text-muted">{{ $mision->brief }}</p>
        @endif

        @if ($mision->instruccion)
            <div class="panel-flat mt-4 p-4">
                <p class="eyebrow">{{ txt('mision.objetivo', 'Tu objetivo') }}</p>
                <div class="mt-2 text-[13px] leading-relaxed text-ink-soft">{!! $mision->instruccion !!}</div>
            </div>
        @endif

        <p class="mt-4 text-[11px] leading-relaxed text-muted">
            {{ txt('mision.instrucciones_nota', 'El cronómetro sigue corriendo mientras lees las instrucciones.') }}
        </p>

        <button type="button" data-cerrar class="btn btn-primary mt-5">
            {{ txt('mision.volver_a_mision', 'Volver a la misión') }}
        </button>
    </div>
</div>
