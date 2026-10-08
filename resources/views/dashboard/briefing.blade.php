@extends('layouts.dashboard')
@section('seccion', 'Contenido')
@section('titulo', 'Instrucciones')

@php $iconos = ['alert','shield','lock','clock','trophy','mail','key','folder','search','phone','user','play']; @endphp

@section('contenido')
<div class="grid gap-4 md:grid-cols-[1fr_340px]">

    <section class="panel p-5">
        <p class="eyebrow">Antes de empezar</p>
        <h2 class="mt-1 text-[18px] font-black text-ink">Instrucciones del juego</h2>
        <p class="mt-2 text-[12.5px] leading-relaxed text-muted">
            Los pasos que ve el participante justo después de entrar, uno por pantalla.
        </p>

        <div class="mt-5 space-y-3">
            @foreach ($pasos as $paso)
                <div class="panel-flat p-4">
                    <form method="POST" action="{{ route('dashboard.briefing.actualizar', $paso) }}" id="paso-{{ $paso->id }}">
                        @csrf @method('PUT')
                    </form>

                    <div class="grid gap-3 sm:grid-cols-[140px_1fr_90px]">
                        <div>
                            <label class="field-label">Icono</label>
                            <select form="paso-{{ $paso->id }}" name="icono" class="field">
                                @foreach ($iconos as $ic)
                                    <option value="{{ $ic }}" @selected($paso->icono === $ic)>{{ $ic }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Título</label>
                            <input form="paso-{{ $paso->id }}" name="titulo" class="field" value="{{ $paso->titulo }}" maxlength="120" required>
                        </div>
                        <div>
                            <label class="field-label">Orden</label>
                            <input form="paso-{{ $paso->id }}" name="orden" type="number" class="field" value="{{ $paso->orden }}" min="0" max="99" required>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="field-label">Texto</label>
                        <textarea form="paso-{{ $paso->id }}" name="texto" class="field" rows="3" maxlength="600" required>{{ $paso->texto }}</textarea>
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <button type="submit" form="paso-{{ $paso->id }}" class="btn btn-primary !w-auto !px-4 !py-2 !text-[12px]">Guardar</button>

                        <form method="POST" action="{{ route('dashboard.briefing.borrar', $paso) }}"
                              onsubmit="return confirm('¿Eliminar este paso?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-ghost !w-auto !px-4 !py-2 !text-[12px]">Eliminar</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <aside class="panel h-fit p-5">
        <p class="eyebrow">Nuevo paso</p>

        <form method="POST" action="{{ route('dashboard.briefing.crear') }}" class="mt-4 space-y-3">
            @csrf

            <div>
                <label class="field-label">Icono</label>
                <select name="icono" class="field">
                    @foreach ($iconos as $ic)<option value="{{ $ic }}">{{ $ic }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="field-label">Título</label>
                <input name="titulo" class="field" maxlength="120" required>
            </div>
            <div>
                <label class="field-label">Texto</label>
                <textarea name="texto" class="field" rows="4" maxlength="600" required></textarea>
            </div>
            <div>
                <label class="field-label">Orden</label>
                <input name="orden" type="number" class="field" value="{{ $pasos->count() }}" min="0" max="99" required>
            </div>

            <button type="submit" class="btn btn-primary">Agregar paso</button>
        </form>
    </aside>
</div>
@endsection
