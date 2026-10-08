@extends('layouts.dashboard')
@section('titulo', 'Acceso al dashboard')

@section('contenido')
<div class="admin-angosto flex min-h-[calc(100dvh-40px)] flex-col justify-center">

    <div class="text-center">
        <div class="anim-pop">
            @include('partials.logo', ['tam' => 'lg'])
        </div>

        <p class="eyebrow anim-in mt-6" style="--i:1">Administración</p>
        <h1 class="anim-in mt-1.5 text-[26px] font-black leading-tight text-ink" style="--i:2">
            Dashboard
        </h1>
        <p class="anim-in mt-2 text-[12.5px] text-muted" style="--i:3">
            {{ config('escape_room.marca.titulo') }}
        </p>
    </div>

    <div class="panel anim-in mt-7 p-5" style="--i:4">

        @if ($errors->any())
            <div class="aviso aviso-error mb-4">
                @include('partials.icono', ['n' => 'alert', 'c' => 'w-4 h-4 shrink-0 mt-px'])
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('dashboard.login') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="field-label">Correo</label>
                <input id="email" name="email" type="email" class="field"
                       value="{{ old('email') }}" placeholder="admin@empresa.com"
                       autocomplete="username" required autofocus>
            </div>

            <div>
                <label for="password" class="field-label">Contraseña</label>
                <input id="password" name="password" type="password" class="field"
                       placeholder="••••••••" autocomplete="current-password" required>
            </div>

            <label class="flex items-center gap-2.5 pt-0.5">
                <input type="checkbox" name="recordarme" value="1"
                       class="h-4 w-4 rounded border-[rgba(46,91,140,.7)] bg-[rgba(5,12,26,.85)] accent-[color:var(--color-cyan)]">
                <span class="text-[11.5px] text-muted">Mantener la sesión abierta</span>
            </label>

            <button type="submit" class="btn btn-primary mt-2">
                @include('partials.icono', ['n' => 'lock', 'c' => 'w-4 h-4'])
                Entrar
            </button>
        </form>
    </div>

    <p class="mt-5 text-center text-[11px] text-muted">
        <a href="{{ route('login') }}" class="font-semibold hover:text-cyan">Ir al juego</a>
    </p>
</div>
@endsection
