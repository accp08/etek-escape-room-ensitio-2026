@extends('layouts.app')
@section('titulo', 'Acceso — ' . ajuste('juego_titulo', 'Ciberamenaza Inminente'))

@section('contenido')
<div class="safe-top flex min-h-full flex-col px-6 pb-8 pt-10">

    <div class="text-center">
        <div class="anim-pop mb-6">
            @include('partials.logo', ['tam' => 'lg'])

            @if ($marca['nombre'])
                <p class="eyebrow mt-3">{{ $marca['nombre'] }}</p>
            @endif
        </div>

        <span class="badge badge-pink anim-in" style="--i:1">
            @include('partials.icono', ['n' => 'alert', 'c' => 'w-3.5 h-3.5'])
            {{ txt('login.alerta', 'Alerta nivel crítico') }}
        </span>

        <h1 class="title-glow anim-in mt-5 text-[34px] leading-[1.05]" style="--i:2">
            {{ ajuste('juego_titulo', 'Ciberamenaza Inminente') }}
        </h1>

        <p class="eyebrow anim-in mt-3" style="--i:3">{{ ajuste('juego_subtitulo', '') }}</p>

        <p class="anim-in mx-auto mt-5 max-w-[30ch] text-sm leading-relaxed text-muted" style="--i:4">
            {{ txt('login.intro', 'Identifícate para unirte al equipo de respuesta.') }}
        </p>
    </div>

    <div class="panel anim-in mt-8 p-5" style="--i:5">

        @if ($errors->any())
            <div class="aviso aviso-error mb-4">
                @include('partials.icono', ['n' => 'alert', 'c' => 'w-4 h-4 shrink-0 mt-px'])
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        @if ($metodoAcceso === 'outlook')
            <a href="{{ route('acceso.outlook') }}" class="btn-outlook mt-2">
                <span aria-hidden="true" class="outlook-logo">
                    <span></span><span></span><span></span><span></span>
                </span>
                <span>Continuar con Microsoft</span>
            </a>
            <p class="mt-4 flex items-center justify-center gap-2 text-center text-[11px] leading-relaxed text-muted">
                <span class="size-1.5 rounded-full bg-cyan-300"></span>
                Acceso institucional · Solo dominios autorizados
            </p>
        @else
        <form action="{{ route('acceso') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label for="correo" class="field-label">{{ txt('login.campo_correo', 'Correo electrónico') }}</label>
                <input id="correo" name="correo" type="email" inputmode="email"
                       class="field" placeholder="nombre@empresa.com" autocomplete="username"
                       autocapitalize="none" spellcheck="false"
                       value="{{ old('correo') }}" maxlength="160" required autofocus>
            </div>

            <button type="submit" class="btn btn-primary mt-2">
                @include('partials.icono', ['n' => 'play', 'c' => 'w-4 h-4'])
                {{ txt('login.boton', 'Iniciar protocolo') }}
            </button>
        </form>
        @endif
    </div>
</div>
@endsection
