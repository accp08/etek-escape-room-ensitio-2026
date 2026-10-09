@extends('layouts.dashboard')
@section('seccion', 'Configuración')
@section('titulo', 'Parámetros')

@php $v = fn ($c, $d = '') => old($c, $ajustes[$c]->valor ?? $d); @endphp

@section('contenido')
<form method="POST" action="{{ route('dashboard.ajustes.guardar') }}" class="grid gap-4 md:grid-cols-2">
    @csrf @method('PUT')

    <section class="panel p-5">
        <p class="eyebrow">Reglas</p>
        <h2 class="mt-1 text-[18px] font-black text-ink">Parámetros del juego</h2>

        <div class="mt-5 space-y-4">
            <div>
                <label class="field-label">Requisito para obtener cada fragmento</label>
                <p class="mt-1.5 text-[11px] leading-relaxed text-muted">
                    Escribir el código correcto de la estación antes de que el cronómetro llegue a cero.
                    El cronómetro arranca al escanear el QR.
                </p>
            </div>

            <div>
                <label class="field-label">Duración por defecto (segundos)</label>
                <input name="duracion_mision" type="number" class="field" min="30" max="3600"
                       value="{{ $v('duracion_mision', 180) }}" required>
                <p class="mt-1.5 text-[11px] text-muted">Cada misión puede tener su propia duración.</p>
            </div>

            <div>
                <label class="field-label">Oportunidades por misión</label>
                <input name="intentos_por_mision" type="number" class="field" min="0" max="99"
                       value="{{ $v('intentos_por_mision', 2) }}" required>
                <p class="mt-1.5 text-[11px] leading-relaxed text-muted">
                    0 = ilimitadas. Cada oportunidad tiene su propio cronómetro y los tiempos
                    se suman. Al agotarlas la misión queda fallada y se reabre al terminar
                    las demás, una sola vez. Cada misión puede tener su propio valor.
                </p>
            </div>
        </div>
    </section>

    <section class="panel h-fit p-5">
        <p class="eyebrow">Identidad</p>
        <h2 class="mt-1 text-[18px] font-black text-ink">Nombre de la experiencia</h2>

        <div class="mt-5 space-y-4">
            <div>
                <label class="field-label">Título</label>
                <input name="juego_titulo" class="field" maxlength="80" value="{{ $v('juego_titulo') }}" required>
            </div>
            <div>
                <label class="field-label">Subtítulo</label>
                <input name="juego_subtitulo" class="field" maxlength="120" value="{{ $v('juego_subtitulo') }}">
            </div>
            <div>
                <label class="field-label">Video de cierre</label>
                <input name="video_final" class="field" maxlength="160" value="{{ $v('video_final') }}"
                       placeholder="escape.mp4">
                <p class="mt-1.5 text-[11px] leading-relaxed text-muted">
                    Archivo dentro de <code class="font-mono">public/</code>. Déjalo vacío para ocultar el botón.
                </p>
            </div>
        </div>

    </section>
    <section class="panel p-5 md:col-span-2">
        <p class="eyebrow">Acceso al juego</p>
        <h2 class="mt-1 text-[18px] font-black text-ink">Método de inicio de sesión</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div>
                <label for="metodo_acceso" class="field-label">¿Cómo ingresarán los participantes?</label>
                <select id="metodo_acceso" name="metodo_acceso" class="field" required>
                    <option value="correo" @selected($v('metodo_acceso', 'correo') === 'correo')>Correo y contraseña actual</option>
                    <option value="outlook" @selected($v('metodo_acceso') === 'outlook')>Cuenta Microsoft Outlook</option>
                </select>
                <p class="mt-1.5 text-[11px] leading-relaxed text-muted">Con Outlook, cualquier cuenta de un dominio permitido puede ingresar. El sistema crea su participante automáticamente en el primer acceso.</p>
            </div>
            <div id="configuracion-dominios-outlook" @class(['hidden' => $v('metodo_acceso') !== 'outlook'])>
                <label for="dominios_outlook" class="field-label">Dominios permitidos</label>
                <textarea id="dominios_outlook" name="dominios_outlook" class="field min-h-24" maxlength="2000"
                          placeholder="empresa.com&#10;filial.com">{{ str_replace(',', "\n", $v('dominios_outlook')) }}</textarea>
                <p class="mt-1.5 text-[11px] leading-relaxed text-muted">Separa los dominios por coma o salto de línea. Ejemplo: empresa.com</p>
            </div>
        </div>
    </section>
    <button type="submit" class="btn btn-primary mt-6 md:col-span-2">Guardar parámetros</button>
</form>
<script>
    document.getElementById('metodo_acceso')?.addEventListener('change', (evento) => {
        const configuracion = document.getElementById('configuracion-dominios-outlook');
        configuracion?.classList.toggle('hidden', evento.target.value !== 'outlook');
    });
</script>
@endsection
