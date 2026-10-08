@extends('layouts.app')
@section('titulo', 'Briefing')

@section('contenido')
<div class="safe-top flex min-h-full flex-col px-6 pb-8 pt-8" data-briefing>

    <div class="flex items-center justify-between">
        <p class="eyebrow">{{ txt('briefing.titulo', 'Briefing de misión') }}</p>
        <a href="{{ route('hub') }}" class="text-[11px] font-semibold text-muted hover:text-cyan">
            {{ txt('briefing.saltar', 'Saltar') }}
        </a>
    </div>

    <div class="mt-6 flex-1">
        @foreach ($pasos as $i => $paso)
            <article data-paso="{{ $i }}" @class(['panel p-6 text-center', 'hidden' => $i !== 0])>
                <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl border border-[rgba(0,229,255,.35)] bg-[rgba(0,229,255,.1)] text-cyan">
                    @include('partials.icono', ['n' => $paso->icono, 'c' => 'w-8 h-8'])
                </div>

                <p class="eyebrow mt-5">Paso {{ $i + 1 }} de {{ count($pasos) }}</p>
                <h2 class="mt-2 text-[22px] font-black leading-tight text-ink">{{ $paso->titulo }}</h2>
                <p class="mt-3 text-sm leading-relaxed text-muted">{{ $paso->texto }}</p>
            </article>
        @endforeach
    </div>

    <div class="mt-6 flex items-center justify-center gap-2">
        @foreach ($pasos as $i => $paso)
            <span data-punto="{{ $i }}"
                  class="h-1.5 rounded-full transition-all {{ $i === 0 ? 'w-6 bg-[color:var(--color-cyan)]' : 'w-1.5 bg-[rgba(46,91,140,.8)]' }}"></span>
        @endforeach
    </div>

    <div class="mt-5 flex gap-3">
        <button type="button" data-prev class="btn btn-ghost" style="flex:0 0 40%" disabled>Anterior</button>
        <button type="button" data-next class="btn btn-primary">Siguiente</button>
        <a href="{{ route('hub') }}" data-comenzar class="btn btn-danger hidden">
            @include('partials.icono', ['n' => 'play', 'c' => 'w-4 h-4'])
            {{ txt('briefing.comenzar', 'Comenzar') }}
        </a>
    </div>
</div>
@endsection
