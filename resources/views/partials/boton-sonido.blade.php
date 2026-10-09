{{-- Activa / silencia el audio [data-proy-audio] de una pantalla proyectada (ver qr.js). --}}
<button type="button" class="proy-boton" data-proy-sonido title="Sonido (M)">
    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M11 5 6 9H3v6h3l5 4V5z"/>
        <path data-sonido-on d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/>
        <path data-sonido-off d="m16 9 6 6M22 9l-6 6"/>
    </svg>
    <span data-proy-sonido-texto>Sonido</span>
</button>
