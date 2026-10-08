/**
 * Consola de recuperación (pantalla proyectada en la sala).
 *
 * El jugador digita con el teclado el código que ve en su celular. Quien
 * decide si es correcto es el servidor; aquí solo se pinta: las casillas, la
 * animación de éxito (con confeti y sonido) o de error, y el top 10 en vivo.
 */

const reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const espera = (ms) => new Promise((r) => setTimeout(r, ms));

const DURACION_EXITO = 12000;
const DURACION_ERROR = 3800;
const REFRESCO_RANKING = 15000;

/* Textos editables en el dashboard; llegan en un bloque JSON de la página. */
let textos = {};

/** Reemplaza {comodines}: plantilla('Puesto #{puesto}', { puesto: 3 }). */
const plantilla = (texto, valores = {}) =>
    String(texto ?? '').replace(/\{(\w+)\}/g, (todo, clave) => (clave in valores ? valores[clave] : todo));

/* ------------------------------------------------------------------ */
/* Sonido: tonos sintetizados, sin archivos                            */
/* ------------------------------------------------------------------ */

let audio = null;

function tono(frecuencia, inicio, duracion, tipo = 'triangle', volumen = 0.18) {
    if (!audio) return;
    const osc = audio.createOscillator();
    const gan = audio.createGain();
    const t = audio.currentTime + inicio;

    osc.type = tipo;
    osc.frequency.setValueAtTime(frecuencia, t);
    gan.gain.setValueAtTime(0.0001, t);
    gan.gain.exponentialRampToValueAtTime(volumen, t + 0.02);
    gan.gain.exponentialRampToValueAtTime(0.0001, t + duracion);
    osc.connect(gan).connect(audio.destination);
    osc.start(t);
    osc.stop(t + duracion + 0.05);
}

const sonidos = {
    tecla: () => tono(1400, 0, 0.05, 'square', 0.03),
    exito: () => [523, 659, 784, 1047, 1319].forEach((f, i) => tono(f, i * 0.11, 0.5)),
    error: () => {
        tono(160, 0, 0.32, 'sawtooth', 0.2);
        tono(120, 0.38, 0.45, 'sawtooth', 0.2);
    },
};

/* ------------------------------------------------------------------ */
/* Confeti                                                             */
/* ------------------------------------------------------------------ */

function lanzarConfeti(lienzo) {
    if (reducido || !lienzo) return;

    const ctx = lienzo.getContext('2d');
    const ancho = (lienzo.width = window.innerWidth);
    const alto = (lienzo.height = window.innerHeight);
    const colores = ['#00e5ff', '#6a5cff', '#ff2da1', '#22c55e', '#9bd5ff', '#ffffff'];

    // Dos ráfagas desde las esquinas inferiores y una lluvia desde arriba.
    const piezas = [];
    for (let i = 0; i < 220; i++) {
        const lado = i % 3;
        const desdeArriba = lado === 2;
        piezas.push({
            x: desdeArriba ? Math.random() * ancho : lado === 0 ? 0 : ancho,
            y: desdeArriba ? -20 - Math.random() * alto * 0.5 : alto,
            vx: desdeArriba ? (Math.random() - 0.5) * 2 : (lado === 0 ? 1 : -1) * (6 + Math.random() * 10),
            vy: desdeArriba ? 2 + Math.random() * 3 : -(14 + Math.random() * 12),
            giro: Math.random() * Math.PI,
            vgiro: (Math.random() - 0.5) * 0.3,
            tam: 6 + Math.random() * 9,
            color: colores[i % colores.length],
        });
    }

    const inicio = performance.now();
    const cuadro = (ahora) => {
        const t = ahora - inicio;
        ctx.clearRect(0, 0, ancho, alto);
        ctx.globalAlpha = Math.max(0, 1 - Math.max(0, t - 3500) / 1200);

        piezas.forEach((p) => {
            p.vy += 0.32;
            p.vx *= 0.99;
            p.x += p.vx;
            p.y += p.vy;
            p.giro += p.vgiro;

            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.giro);
            ctx.fillStyle = p.color;
            ctx.fillRect(-p.tam / 2, -p.tam / 4, p.tam, p.tam / 2);
            ctx.restore();
        });

        if (t < 4700) requestAnimationFrame(cuadro);
        else ctx.clearRect(0, 0, ancho, alto);
    };

    requestAnimationFrame(cuadro);
}

/* ------------------------------------------------------------------ */
/* Ranking                                                             */
/* ------------------------------------------------------------------ */

function pintarRanking(lista, filas, destacar = null) {
    lista.replaceChildren();

    if (!filas.length) {
        const vacio = document.createElement('li');
        vacio.className = 'cons-vacio';
        vacio.textContent = textos.top_vacio;
        lista.append(vacio);
        return;
    }

    // textContent, nunca innerHTML: los nombres los escribe el administrador.
    filas.forEach((f, i) => {
        const li = document.createElement('li');
        li.className = 'cons-fila' + (f.partida === destacar ? ' cons-fila-nueva' : '');
        li.dataset.partida = f.partida;
        li.style.setProperty('--i', i);

        const puesto = document.createElement('span');
        puesto.className = 'cons-fila-puesto';
        puesto.textContent = f.puesto;

        const nombre = document.createElement('span');
        nombre.className = 'cons-fila-nombre';
        const fuerte = document.createElement('strong');
        fuerte.textContent = f.nombre;
        nombre.append(fuerte);
        if (f.area) {
            const area = document.createElement('small');
            area.textContent = f.area;
            nombre.append(area);
        }

        const aciertos = document.createElement('span');
        aciertos.className = 'cons-fila-aciertos';
        aciertos.textContent = `${f.aciertos}/${f.total}`;

        const tiempo = document.createElement('span');
        tiempo.className = 'cons-fila-tiempo';
        tiempo.textContent = f.tiempo;

        li.append(puesto, nombre, aciertos, tiempo);
        lista.append(li);
    });
}

/* ------------------------------------------------------------------ */
/* Consola                                                             */
/* ------------------------------------------------------------------ */

export function iniciarConsola() {
    const cuerpo = document.querySelector('[data-consola]');
    if (!cuerpo) return;

    try {
        textos = JSON.parse(cuerpo.querySelector('[data-textos-consola]')?.textContent || '{}');
    } catch {
        textos = {};
    }

    const terminal = cuerpo.querySelector('[data-terminal]');
    const entrada = cuerpo.querySelector('[data-entrada]');
    const casillas = [...cuerpo.querySelectorAll('[data-casilla]')];
    const aviso = cuerpo.querySelector('[data-aviso]');
    const lista = cuerpo.querySelector('[data-lista]');
    const log = cuerpo.querySelector('[data-log]');
    const confeti = cuerpo.querySelector('[data-confeti]');
    const largo = casillas.length;

    let ocupado = false;
    let temporizador = null;

    const estado = (nombre) => {
        terminal.dataset.estado = nombre;
    };

    const enfocar = () => {
        if (!ocupado) entrada.focus({ preventScroll: true });
    };

    const pintarCasillas = () => {
        const valor = entrada.value;
        casillas.forEach((c, i) => {
            c.textContent = valor[i] ?? '';
            c.dataset.llena = i < valor.length ? 'si' : 'no';
            c.dataset.actual = i === valor.length ? 'si' : 'no';
        });
    };

    const avisar = (texto) => {
        aviso.textContent = texto;
        aviso.classList.toggle('cons-aviso-visible', Boolean(texto));
    };

    const reiniciar = () => {
        clearTimeout(temporizador);
        entrada.value = '';
        ocupado = false;
        cuerpo.classList.remove('cons-con-alarma');
        avisar('');
        pintarCasillas();
        estado('espera');
        enfocar();
    };

    const sacudir = () => {
        const caja = cuerpo.querySelector('[data-casillas]');
        caja.classList.remove('cons-sacudir');
        void caja.offsetWidth; // reinicia la animación
        caja.classList.add('cons-sacudir');
    };

    // Mensajes que se escriben mientras «descifra»: dan suspenso.
    const escribirLog = async () => {
        const lineas = String(textos.log ?? '').split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
        for (const linea of lineas) {
            if (terminal.dataset.estado !== 'verificando') return;
            log.textContent = `> ${linea}`;
            await espera(480);
        }
    };

    const exito = (datos) => {
        // El nombre va resaltado dentro del saludo: «¡Bien hecho, <strong>Ana</strong>!»
        const saludo = cuerpo.querySelector('[data-saludo]');
        const [antes, ...despues] = String(textos.saludo ?? '{nombre}').split('{nombre}');
        const nombre = document.createElement('strong');
        nombre.textContent = datos.nombre;
        saludo.replaceChildren(antes, ...(despues.length ? [nombre, despues.join(datos.nombre)] : []));

        cuerpo.querySelector('[data-tiempo]').textContent = datos.tiempo;
        cuerpo.querySelector('[data-puesto]').textContent = datos.puesto ? `#${datos.puesto}` : '—';

        const mensaje = cuerpo.querySelector('[data-mensaje]');
        mensaje.dataset.top = datos.en_top ? 'si' : 'no';
        mensaje.textContent = plantilla(
            datos.repetido ? textos.repetido : datos.en_top ? textos.top : textos.puesto,
            { puesto: datos.puesto, total: datos.total },
        );

        estado('exito');
        sonidos.exito();
        lanzarConfeti(confeti);
        pintarRanking(lista, datos.ranking, datos.partida);

        temporizador = setTimeout(reiniciar, DURACION_EXITO);
    };

    const error = (texto = null) => {
        cuerpo.querySelector('[data-error-texto]').textContent = texto ?? textos.error;

        estado('error');
        cuerpo.classList.add('cons-con-alarma');
        sonidos.error();

        temporizador = setTimeout(reiniciar, DURACION_ERROR);
    };

    const enviar = async () => {
        if (ocupado) return;

        const codigo = entrada.value;
        if (codigo.length < largo) {
            sacudir();
            avisar(codigo.length ? plantilla(textos.faltan, { faltan: largo - codigo.length }) : textos.vacio);
            return;
        }

        ocupado = true;
        avisar('');
        estado('verificando');
        escribirLog();

        try {
            const [respuesta] = await Promise.all([
                fetch(cuerpo.dataset.verificar, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ codigo }),
                }),
                espera(reducido ? 300 : 1500),
            ]);

            if (respuesta.ok) return exito(await respuesta.json());
            if (respuesta.status === 429) return error(textos.limite);

            return error();
        } catch {
            return error(textos.sin_conexion);
        }
    };

    // El sonido solo se puede activar tras una acción del usuario.
    const activarAudio = () => {
        if (audio) return;
        try {
            audio = new (window.AudioContext || window.webkitAudioContext)();
        } catch {
            audio = null;
        }
    };

    entrada.addEventListener('input', () => {
        const limpio = entrada.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, largo);
        if (limpio !== entrada.value) entrada.value = limpio;
        avisar('');
        pintarCasillas();
        sonidos.tecla();
    });

    entrada.addEventListener('keydown', (e) => {
        activarAudio();
        if (e.key === 'Enter') {
            e.preventDefault();
            enviar();
        }
    });

    // Durante el éxito, cualquier tecla deja la consola lista para el siguiente.
    document.addEventListener('keydown', (e) => {
        activarAudio();
        if (terminal.dataset.estado === 'exito' && (e.key === 'Enter' || e.key === 'Escape' || e.key === ' ')) {
            e.preventDefault();
            reiniciar();
        }
    });

    cuerpo.querySelector('[data-enviar]').addEventListener('click', () => {
        activarAudio();
        enviar();
    });

    // El campo siempre conserva el foco: la pantalla no tiene mouse a mano.
    document.addEventListener('click', enfocar);
    entrada.addEventListener('blur', () => setTimeout(enfocar, 50));

    // El top 10 se refresca solo.
    setInterval(async () => {
        if (ocupado) return;
        try {
            const respuesta = await fetch(cuerpo.dataset.ranking, { headers: { Accept: 'application/json' } });
            if (respuesta.ok) pintarRanking(lista, (await respuesta.json()).ranking);
        } catch {
            // Sin red: se conserva el último ranking.
        }
    }, REFRESCO_RANKING);

    pintarCasillas();
    enfocar();
}

/* ------------------------------------------------------------------ */
/* Celular: espera a que el código se digite en la consola             */
/* ------------------------------------------------------------------ */

export function esperarConsola() {
    const raiz = document.querySelector('[data-espera-consola]');
    if (!raiz) return;

    setInterval(async () => {
        try {
            const respuesta = await fetch(raiz.dataset.esperaConsola, { headers: { Accept: 'application/json' } });
            if (respuesta.ok && (await respuesta.json()).ingresado) {
                window.location.href = raiz.dataset.victoria;
            }
        } catch {
            // Sin red: se intenta en la siguiente vuelta.
        }
    }, 4000);
}
