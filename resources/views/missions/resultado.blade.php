{{-- Resultado real de la misión, calculado en el servidor. --}}
@extends('layouts.app')
@section('titulo', 'Resultado — ' . $mision->titulo)

@php
    $estado = $juicio['resultado'];   // superada | reintento | fallada | tiempo
    $exito  = $estado === 'superada';

    // Queda otra oportunidad tanto al fallar la respuesta como al agotar el tiempo.
    $puedeReintentar = ! $exito && $avance->abierta();

    $copias = [
        'superada'  => ['icono' => 'check', 'eyebrow' => 'Misión superada',
                        'titulo' => txt('resultado.exito_titulo', 'Fragmento recuperado'),
                        'texto'  => txt('resultado.exito_texto', '')],
        'reintento' => ['icono' => 'alert', 'eyebrow' => 'Código incorrecto',
                        'titulo' => txt('resultado.reintento_titulo', 'Tienes otra oportunidad'),
                        'texto'  => txt('resultado.reintento_texto', '')],
        'fallada'   => ['icono' => 'x', 'eyebrow' => 'Misión fallida',
                        'titulo' => txt('resultado.fallo_titulo', 'Sin intentos disponibles'),
                        'texto'  => txt('resultado.fallo_texto', '')],
        'tiempo'    => ['icono' => 'clock', 'eyebrow' => 'Tiempo agotado',
                        'titulo' => txt('resultado.tiempo_titulo', 'El cronómetro llegó a cero'),
                        'texto'  => txt('resultado.tiempo_texto', '')],
    ];

    $copia = $copias[$estado] ?? $copias['fallada'];
@endphp

@section('contenido')
<div class="safe-top flex min-h-full flex-col px-6 pb-8 pt-10 text-center">

    <div class="flex-1">
        <div @class([
            'anim-pop mx-auto grid h-24 w-24 place-items-center rounded-full border-2',
            'anim-ring border-[rgba(34,197,94,.5)] bg-[rgba(34,197,94,.1)] text-ok' => $exito,
            'anim-ring-pink border-[rgba(255,45,161,.5)] bg-[rgba(255,45,161,.1)] text-pink-soft' => ! $exito,
        ])>
            @include('partials.icono', ['n' => $copia['icono'], 'c' => 'w-12 h-12'])
        </div>

        <p class="eyebrow anim-in mt-6" style="--i:2">{{ $copia['eyebrow'] }}</p>
        <h1 class="anim-in mt-2 text-[26px] font-black leading-tight text-ink" style="--i:3">{{ $copia['titulo'] }}</h1>
        <p class="anim-in mx-auto mt-3 max-w-[32ch] text-sm leading-relaxed text-muted" style="--i:4">{{ $copia['texto'] }}</p>

        @if ($exito)
            <div class="panel anim-in mt-7 p-6" style="--i:5">
                <p class="eyebrow">Fragmento de {{ $mision->titulo }}</p>
                <p class="code-chip mt-3 inline-block text-3xl">{{ $fragmento }}</p>
            </div>
        @endif

        <div class="anim-in mt-5 grid grid-cols-2 gap-2.5" style="--i:6">
            <div class="panel-flat p-3">
                <p class="timer text-lg">{{ sprintf('%02d:%02d', intdiv($avance->segundos, 60), $avance->segundos % 60) }}</p>
                <p class="eyebrow mt-1">Tiempo total</p>
            </div>
            <div class="panel-flat p-3">
                <p class="text-lg font-black text-ink">{{ $avance->intentos_usados }}</p>
                <p class="eyebrow mt-1">Intentos</p>
            </div>
        </div>

        @if ($avance->fallada() && ! $avance->reactivada)
            <p class="mt-5 text-[11.5px] leading-relaxed text-muted">
                Cuando termines las demás misiones, esta se reabrirá para un último intento.
            </p>
        @endif
    </div>

    <div class="mt-8 space-y-3">
        @if ($puedeReintentar)
            {{-- La siguiente oportunidad arranca al volver a escanear el QR. --}}
            <a href="{{ route('mision', $mision->slug) }}" class="btn btn-danger">
                @include('partials.icono', ['n' => 'play', 'c' => 'w-4 h-4'])
                Intentar de nuevo
            </a>
        @endif

        <a href="{{ route('hub') }}" class="btn {{ $puedeReintentar ? 'btn-ghost' : 'btn-primary' }}">
            @include('partials.icono', ['n' => 'shield', 'c' => 'w-4 h-4'])
            Volver al centro de mando
        </a>
    </div>
</div>
@endsection
