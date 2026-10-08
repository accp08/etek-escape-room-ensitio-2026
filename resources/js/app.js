/**
 * Ciberamenaza Inminente — interacciones de la interfaz.
 *
 * Solo pinta: el cronómetro, las hojas a pantalla completa, el briefing y
 * el dashboard. Quien decide (tiempo, intentos, códigos) es el servidor.
 */

import { iniciarEditores } from './editor';
import { iniciarEscaner, iniciarProyeccion, pintarQrs } from './qr';

const reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ------------------------------------------------------------------ */
/* Cronómetro (visual: cuenta regresiva, color de alerta y espejos)    */
/* ------------------------------------------------------------------ */

function iniciarCronometro() {
    const salida = document.querySelector('[data-timer]');
    if (!salida) return;

    const barra = document.querySelector('[data-timer-bar]');
    const espejos = document.querySelectorAll('[data-espejo-timer]');
    const total = Number(salida.dataset.seconds) || 180;
    let restante = total;

    const pintar = () => {
        const min = String(Math.floor(restante / 60)).padStart(2, '0');
        const seg = String(restante % 60).padStart(2, '0');
        const texto = `${min}:${seg}`;

        salida.textContent = texto;
        espejos.forEach((e) => {
            e.textContent = texto;
            e.classList.toggle('timer-alert', restante <= 30);
        });

        if (barra) {
            barra.firstElementChild.style.width = `${Math.max(0, (restante / total) * 100)}%`;
            barra.classList.toggle('bar-alert', restante <= 30);
        }
        salida.classList.toggle('timer-alert', restante <= 30);
    };

    pintar();

    const id = setInterval(() => {
        restante = Math.max(0, restante - 1);
        pintar();

        if (restante === 0) {
            clearInterval(id);

            // Se envía lo que haya: el servidor cierra la misión como "tiempo agotado".
            const formulario = document.querySelector('#form-mision');
            if (formulario && !formulario.dataset.enviado) {
                formulario.dataset.enviado = 'si';
                formulario.submit();
            }
        }
    }, 1000);
}

/* ------------------------------------------------------------------ */
/* Hojas a pantalla completa                                           */
/* ------------------------------------------------------------------ */

function abrirHoja(hoja) {
    hoja.classList.remove('hidden');
    hoja.querySelector('[data-panel]')?.classList.add('sheet-in');
    hoja.querySelector('.absolute.inset-0')?.classList.add('backdrop-in');

    // Congela el desplazamiento de la pantalla que queda detrás.
    document.querySelector('.device-scroll')?.classList.add('overflow-hidden');
}

function cerrarHoja(hoja) {
    const panel = hoja.querySelector('[data-panel]');

    const terminar = () => {
        hoja.classList.add('hidden');
        panel?.classList.remove('sheet-out', 'sheet-in');
        document.querySelector('.device-scroll')?.classList.remove('overflow-hidden');
    };

    if (reducido || !panel) return terminar();

    panel.classList.remove('sheet-in');
    panel.classList.add('sheet-out');
    panel.addEventListener('animationend', terminar, { once: true });
}

function conectarCierres(hoja) {
    hoja.querySelectorAll('[data-cerrar]').forEach((b) =>
        b.addEventListener('click', () => cerrarHoja(hoja))
    );
}

/* ------------------------------------------------------------------ */
/* Instrucciones de la misión, en cualquier momento                    */
/* ------------------------------------------------------------------ */

function iniciarHojasMision() {
    const instrucciones = document.querySelector('[data-instrucciones]');
    if (!instrucciones) return;

    document.querySelector('[data-abrir-instrucciones]')
        ?.addEventListener('click', () => abrirHoja(instrucciones));
    conectarCierres(instrucciones);
}

/* ------------------------------------------------------------------ */
/* Briefing paso a paso                                                */
/* ------------------------------------------------------------------ */

function iniciarBriefing(raiz) {
    const pasos = raiz.querySelectorAll('[data-paso]');
    const puntos = raiz.querySelectorAll('[data-punto]');
    const anterior = raiz.querySelector('[data-prev]');
    const siguiente = raiz.querySelector('[data-next]');
    const comenzar = raiz.querySelector('[data-comenzar]');
    let i = 0;

    const mostrar = () => {
        pasos.forEach((p, k) => {
            p.classList.toggle('hidden', k !== i);
            p.classList.remove('step-enter');
        });

        if (!reducido) {
            pasos[i].classList.add('step-enter');
            pasos[i].addEventListener('animationend', () => pasos[i].classList.remove('step-enter'), { once: true });
        }

        puntos.forEach((p, k) => {
            p.classList.toggle('w-6', k === i);
            p.classList.toggle('w-1.5', k !== i);
            p.classList.toggle('bg-[color:var(--color-cyan)]', k === i);
            p.classList.toggle('bg-[rgba(46,91,140,.8)]', k !== i);
        });

        anterior.disabled = i === 0;

        const ultimo = i === pasos.length - 1;
        siguiente.classList.toggle('hidden', ultimo);
        comenzar.classList.toggle('hidden', !ultimo);
    };

    anterior.addEventListener('click', () => { if (i > 0) { i -= 1; mostrar(); } });
    siguiente.addEventListener('click', () => { if (i < pasos.length - 1) { i += 1; mostrar(); } });

    mostrar();
}

/* ------------------------------------------------------------------ */
/* Dashboard: textos de interfaz (pestañas, búsqueda, cambios)         */
/* ------------------------------------------------------------------ */

function iniciarTextos(form) {
    const pestanas = [...form.querySelectorAll('[data-pestana]')];
    const paneles = [...form.querySelectorAll('[data-panel-pestana]')];
    const campos = [...form.querySelectorAll('[data-campo-texto]')];
    const buscador = form.querySelector('[data-buscar-texto]');
    const actual = form.querySelector('[data-pestana-actual]');
    const sinResultados = form.querySelector('[data-sin-resultados]');
    const estado = form.querySelector('[data-estado-cambios]');
    let enviando = false;

    const activar = (clave) => {
        if (!pestanas.some((p) => p.dataset.pestana === clave)) clave = pestanas[0].dataset.pestana;

        pestanas.forEach((p) => p.setAttribute('aria-selected', p.dataset.pestana === clave ? 'true' : 'false'));
        paneles.forEach((p) => p.classList.toggle('hidden', p.dataset.panelPestana !== clave));
        actual.value = clave;
        history.replaceState(null, '', `#${clave}`);
    };

    pestanas.forEach((p) => p.addEventListener('click', () => {
        buscador.value = '';
        filtrar();
        activar(p.dataset.pestana);
    }));

    // Búsqueda: muestra coincidencias de todas las pestañas a la vez.
    const filtrar = () => {
        const q = buscador.value.trim().toLowerCase();
        form.dataset.buscando = q ? 'true' : 'false';

        if (!q) {
            campos.forEach((c) => c.classList.remove('hidden'));
            form.querySelectorAll('[data-pantalla], [data-indice]').forEach((e) => e.classList.remove('hidden'));
            sinResultados.classList.add('hidden');
            activar(actual.value);
            return;
        }

        let total = 0;
        campos.forEach((c) => {
            const valor = c.querySelector('.field').value.toLowerCase();
            const coincide = c.dataset.busqueda.includes(q) || valor.includes(q);
            c.classList.toggle('hidden', !coincide);
            if (coincide) total++;
        });

        form.querySelectorAll('[data-pantalla]').forEach((s) =>
            s.classList.toggle('hidden', !s.querySelector('[data-campo-texto]:not(.hidden)'))
        );
        form.querySelectorAll('[data-indice]').forEach((n) => n.classList.add('hidden'));
        paneles.forEach((p) => p.classList.toggle('hidden', !p.querySelector('[data-pantalla]:not(.hidden)')));
        sinResultados.classList.toggle('hidden', total > 0);
    };

    buscador.addEventListener('input', filtrar);

    // Cambios sin guardar: se marcan el campo y su pestaña.
    const originales = new Map(campos.map((c) => [c, c.querySelector('.field').value]));

    const revisar = () => {
        const sucias = new Set();
        let n = 0;

        campos.forEach((c) => {
            const cambiado = c.querySelector('.field').value !== originales.get(c);
            c.dataset.modificado = cambiado ? 'true' : 'false';
            if (cambiado) {
                n++;
                sucias.add(c.closest('[data-panel-pestana]').dataset.panelPestana);
            }
        });

        form.querySelectorAll('[data-cambios-en]').forEach((m) => m.classList.toggle('hidden', !sucias.has(m.dataset.cambiosEn)));
        estado.textContent = n === 0 ? 'Sin cambios pendientes.' : `${n} ${n === 1 ? 'texto modificado' : 'textos modificados'} sin guardar.`;
        estado.classList.toggle('text-pink-soft', n > 0);

        return n;
    };

    form.addEventListener('input', (e) => { if (e.target.closest('[data-campo-texto]')) revisar(); });
    form.addEventListener('submit', () => { enviando = true; });
    window.addEventListener('beforeunload', (e) => {
        if (!enviando && revisar() > 0) e.preventDefault();
    });

    activar(location.hash.slice(1));
}

/* ------------------------------------------------------------------ */
/* Arranque                                                            */
/* ------------------------------------------------------------------ */

document.addEventListener('DOMContentLoaded', () => {
    iniciarCronometro();

    const briefing = document.querySelector('[data-briefing]');
    if (briefing) iniciarBriefing(briefing);

    // Dashboard: menú lateral plegable en pantallas angostas.
    const lateral = document.querySelector('[data-lateral]');
    lateral?.querySelector('[data-lateral-toggle]')?.addEventListener('click', () => {
        lateral.dataset.abierta = lateral.dataset.abierta === 'true' ? 'false' : 'true';
    });

    // Misión en curso: instrucciones.
    iniciarHojasMision();

    // Hojas genéricas: un botón data-abrir-hoja="x" abre la hoja data-hoja="x".
    document.querySelectorAll('[data-hoja]').forEach((hoja) => {
        conectarCierres(hoja);
        document.querySelectorAll(`[data-abrir-hoja="${hoja.dataset.hoja}"]`)
            .forEach((b) => b.addEventListener('click', () => abrirHoja(hoja)));
    });

    // Dashboard: editores de contenido enriquecido.
    iniciarEditores();

    // Misión: lector del QR de la estación. Dashboard: QR y pantalla de proyección.
    iniciarEscaner();
    pintarQrs();
    iniciarProyeccion();

    // Dashboard: vista previa del logo antes de guardarlo.
    const campoLogo = document.querySelector('#logo');
    if (campoLogo) {
        campoLogo.addEventListener('change', () => {
            const archivo = campoLogo.files?.[0];
            const etiqueta = document.querySelector('[data-nombre-archivo]');
            const placa = document.querySelector('[data-placa-previa]');
            const img = document.querySelector('[data-img-previa]');
            const sinLogo = document.querySelector('[data-sin-logo]');

            if (!archivo) return;

            if (etiqueta) etiqueta.textContent = archivo.name;

            const url = URL.createObjectURL(archivo);
            img.src = url;
            img.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });

            placa.style.display = '';
            if (sinLogo) sinLogo.style.display = 'none';
        });
    }

    // Dashboard: textos de interfaz por pestañas.
    const formTextos = document.querySelector('[data-textos]');
    if (formTextos) iniciarTextos(formTextos);

    // Dashboard: vista previa de la imagen de una escena o carta.
    document.querySelectorAll('[data-campo-imagen]').forEach((campo) => {
        const entrada = campo.querySelector('[data-imagen-input]');

        entrada?.addEventListener('change', () => {
            const archivo = entrada.files?.[0];
            if (!archivo) return;

            const img = campo.querySelector('[data-imagen-previa]');
            const url = URL.createObjectURL(archivo);
            img.src = url;
            img.classList.remove('hidden');
            img.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });

            campo.querySelector('[data-imagen-vacia]')?.classList.add('hidden');
            campo.querySelector('[data-imagen-nombre]').textContent = archivo.name;
        });
    });

    // Certificado: video de cierre en una hoja del dispositivo.
    const hojaVideo = document.querySelector('[data-hoja-video]');
    if (hojaVideo) {
        const video = hojaVideo.querySelector('[data-video]');

        document.querySelector('[data-abrir-video]')?.addEventListener('click', () => {
            // El archivo pesa ~21 MB: se pide solo al abrir, no al cargar la página.
            if (!video.src) video.src = video.dataset.fuente;

            abrirHoja(hojaVideo);
            video.play().catch(() => {}); // si el navegador bloquea el autoplay, queda el control
        });

        hojaVideo.querySelectorAll('[data-cerrar]').forEach((boton) =>
            boton.addEventListener('click', () => {
                video.pause();
                cerrarHoja(hojaVideo);
            })
        );
    }

    // Certificado: abre el diálogo de impresión del navegador.
    // La hoja se arma con las reglas @media print de app.css.
    document.querySelector('[data-descargar-certificado]')?.addEventListener('click', (e) => {
        const boton = e.currentTarget;

        // Se avisa al servidor antes de abrir el diálogo: `keepalive` permite que
        // la petición sobreviva aunque el navegador bloquee la pestaña al imprimir.
        fetch(boton.dataset.aviso, {
            method: 'POST',
            keepalive: true,
            headers: {
                'X-CSRF-TOKEN': boton.dataset.token,
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).catch(() => {});

        window.print();
    });

    // Cerrar la hoja abierta con Escape.
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        const abierta = document.querySelector(
            '[data-hoja-video]:not(.hidden), [data-instrucciones]:not(.hidden), [data-hoja]:not(.hidden)'
        );
        if (!abierta) return;

        abierta.querySelector('[data-video]')?.pause();
        cerrarHoja(abierta);
    });
});

/* Service worker (PWA) */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
