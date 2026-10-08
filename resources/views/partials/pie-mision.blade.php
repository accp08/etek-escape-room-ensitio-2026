{{-- Pie fijo: oportunidades restantes y envío del código. --}}
<footer class="safe-bottom shrink-0 border-t border-[rgba(46,91,140,.4)] bg-[rgba(5,12,26,.9)] px-4 pt-3 backdrop-blur-md">
    <div class="mb-2.5 flex items-center justify-between text-[11px]">
        <span class="text-muted">Oportunidades restantes</span>
        <span class="font-mono font-bold text-cyan">{{ $avance->intentosRestantesLegible() }}</span>
    </div>

    <button type="submit" form="form-mision" class="btn btn-primary">
        @include('partials.icono', ['n' => 'lock', 'c' => 'w-4 h-4'])
        {{ txt('mision.validar', 'Validar código') }}
    </button>
</footer>
