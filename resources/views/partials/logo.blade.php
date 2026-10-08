{{-- Logo de la empresa sobre placa blanca.
     La placa garantiza que cualquier logo se lea bien sobre la paleta oscura,
     sin importar sus colores. La marca viene del dashboard (tabla empresas) y,
     si no hay ninguna configurada, de config/escape_room.php.

     Uso: @include('partials.logo', ['tam' => 'sm' | 'md' | 'lg' | 'cert']) --}}
@php $tam = $tam ?? 'md'; @endphp

@if (! empty($marca['logo']))
    <span class="placa-logo placa-{{ $tam }}">
        <img src="{{ $marca['logo'] }}" alt="{{ $marca['nombre'] }}">
    </span>
@endif
