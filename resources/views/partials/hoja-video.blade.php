{{-- Video de cierre a pantalla completa dentro del marco del celular. Lo
     abre un botón data-abrir-video (ver app.js). El archivo no se descarga
     hasta que se abre (preload="none" y el src se asigna al abrir): son ~21 MB. --}}
<div class="hoja solo-pantalla fixed inset-0 z-50 hidden" data-hoja-video>
    <div class="absolute inset-0 bg-[rgba(3,7,16,.92)] backdrop-blur-sm" data-cerrar></div>

    <div class="absolute inset-0 flex flex-col bg-[color:var(--color-bg-deep)]" data-panel>

        <div class="safe-top flex shrink-0 items-center gap-3 border-b border-[rgba(46,91,140,.4)] bg-[rgba(5,12,26,.9)] px-4 py-3">
            <button type="button" data-cerrar
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-[rgba(46,91,140,.5)] text-muted hover:text-cyan"
                    aria-label="Cerrar el video">
                @include('partials.icono', ['n' => 'x', 'c' => 'w-4 h-4'])
            </button>
            <div class="min-w-0 flex-1">
                <p class="eyebrow">Ciberamenaza Inminente</p>
                <p class="truncate text-[12.5px] font-bold text-ink">La experiencia</p>
            </div>
        </div>

        {{-- El video es apaisado y el marco es vertical: se enmarca centrado
             sobre el fondo del juego en lugar de dejar bandas negras sueltas. --}}
        <div class="flex flex-1 flex-col items-center justify-center gap-5 px-4">
            <div class="w-full overflow-hidden rounded-2xl border border-[rgba(0,229,255,.35)] bg-black shadow-[0_0_46px_rgba(0,229,255,.16)]">
                <video data-video class="block h-auto w-full"
                       controls playsinline preload="none"
                       data-fuente="{{ route('video.final') }}">
                    Tu navegador no puede reproducir este video.
                </video>
            </div>

            <p class="max-w-[30ch] text-center text-[11px] leading-relaxed text-muted">
                Gira el teléfono o usa el botón de pantalla completa del reproductor
                para verlo más grande.
            </p>
        </div>

        <div class="safe-bottom shrink-0 border-t border-[rgba(46,91,140,.4)] bg-[rgba(5,12,26,.92)] px-4 pt-3">
            <button type="button" data-cerrar class="btn btn-ghost !py-3 !text-[13px]">
                Cerrar
            </button>
        </div>
    </div>
</div>
