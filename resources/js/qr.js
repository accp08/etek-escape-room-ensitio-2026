/**
 * Juego en sitio: lector del QR de la estación y QR imprimibles del dashboard.
 *
 * El QR de cada estación contiene la URL /mision/{slug}/activar/{token}. El
 * lector solo navega a esa URL; quien activa la misión y arranca el
 * cronómetro es el servidor. Las librerías se cargan bajo demanda para no
 * pesar en el resto de pantallas.
 */

const RUTA_ACTIVACION = /\/mision\/[^/]+\/activar\/[^/]+\/?$/;

/* ------------------------------------------------------------------ */
/* Lector (pantalla de la misión en sitio)                             */
/* ------------------------------------------------------------------ */

/**
 * Devuelve una función que intenta leer un QR de un <video>. Usa el
 * BarcodeDetector del navegador si existe (Chrome en Android) y jsQR si no
 * (Safari en iPhone).
 */
async function crearLector() {
    if ('BarcodeDetector' in window) {
        try {
            const formatos = await window.BarcodeDetector.getSupportedFormats();
            if (formatos.includes('qr_code')) {
                const detector = new window.BarcodeDetector({ formats: ['qr_code'] });
                return async (video) => (await detector.detect(video))[0]?.rawValue ?? null;
            }
        } catch {
            // Se sigue con jsQR.
        }
    }

    const { default: jsQR } = await import('jsqr');
    const lienzo = document.createElement('canvas');
    const contexto = lienzo.getContext('2d', { willReadFrequently: true });

    return async (video) => {
        const { videoWidth: ancho, videoHeight: alto } = video;
        if (!ancho || !alto) return null;

        // Se reduce el cuadro: leer a 640 px basta y no recalienta el celular.
        const escala = Math.min(1, 640 / Math.max(ancho, alto));
        lienzo.width = Math.round(ancho * escala);
        lienzo.height = Math.round(alto * escala);
        contexto.drawImage(video, 0, 0, lienzo.width, lienzo.height);

        const imagen = contexto.getImageData(0, 0, lienzo.width, lienzo.height);

        return jsQR(imagen.data, imagen.width, imagen.height, { inversionAttempts: 'dontInvert' })?.data ?? null;
    };
}

/**
 * URL a la que lleva el QR leído, o null si no es un QR del juego. Se navega
 * siempre al servidor actual, aunque el QR se haya generado con otro dominio.
 */
function destinoDe(texto) {
    let url;
    try {
        url = new URL(texto, window.location.href);
    } catch {
        return null;
    }

    return RUTA_ACTIVACION.test(url.pathname) ? window.location.origin + url.pathname : null;
}

export function iniciarEscaner() {
    const raiz = document.querySelector('[data-escaner]');
    if (!raiz) return;

    const abrir = document.querySelector('[data-escaner-abrir]');
    const cerrar = document.querySelector('[data-escaner-cerrar]');
    const visor = raiz.querySelector('[data-escaner-visor]');
    const video = raiz.querySelector('[data-escaner-video]');
    const estado = raiz.querySelector('[data-escaner-estado]');

    let flujo = null;
    let activo = false;

    const avisar = (texto) => {
        estado.textContent = texto;
        estado.classList.toggle('hidden', !texto);
    };

    const detener = () => {
        activo = false;
        flujo?.getTracks().forEach((pista) => pista.stop());
        flujo = null;
        video.srcObject = null;
        visor.classList.add('hidden');
        cerrar.classList.add('hidden');
        abrir.classList.remove('hidden');
    };

    abrir.addEventListener('click', async () => {
        avisar('');

        // Los navegadores solo dan la cámara en HTTPS (o en localhost).
        if (!window.isSecureContext) {
            avisar('La cámara solo funciona si el juego se abre con https://. Mientras tanto, escanea el QR con la cámara de tu celular.');
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            avisar('Este navegador no permite usar la cámara aquí. Escanea el QR con la cámara de tu celular.');
            return;
        }

        let leer;
        try {
            flujo = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            });
            leer = await crearLector();
        } catch (error) {
            detener();
            avisar(error?.name === 'NotAllowedError'
                ? 'Necesitamos permiso para usar la cámara. Actívalo en tu navegador o escanea el QR con la cámara de tu celular.'
                : 'No fue posible abrir la cámara. Escanea el QR con la cámara de tu celular.');
            return;
        }

        video.srcObject = flujo;
        await video.play().catch(() => {});

        visor.classList.remove('hidden');
        abrir.classList.add('hidden');
        cerrar.classList.remove('hidden');
        visor.scrollIntoView({ block: 'center', behavior: 'smooth' });

        activo = true;

        const ciclo = async () => {
            if (!activo) return;

            let texto = null;
            try {
                if (video.readyState >= 2) texto = await leer(video);
            } catch {
                // Cuadro ilegible: se intenta con el siguiente.
            }

            if (texto) {
                const destino = destinoDe(texto);

                if (destino) {
                    detener();
                    abrir.disabled = true;
                    avisar('');
                    navigator.vibrate?.(80);
                    window.location.href = destino;
                    return;
                }

                avisar('Este código QR no pertenece al juego. Busca el QR de la estación de la misión.');
            }

            setTimeout(ciclo, 200);
        };

        ciclo();
    });

    cerrar.addEventListener('click', detener);

    // Al salir de la pantalla se apaga la cámara.
    window.addEventListener('pagehide', detener);
}

/* ------------------------------------------------------------------ */
/* QR imprimibles (dashboard)                                          */
/* ------------------------------------------------------------------ */

/** Pinta el QR del lienzo. data-qr-nivel sube la corrección de errores (proyección). */
function pintarQr(QRCode, lienzo) {
    QRCode.toCanvas(lienzo, lienzo.dataset.qr, {
        margin: 1,
        errorCorrectionLevel: lienzo.dataset.qrNivel || 'M',
        width: Number(lienzo.getAttribute('width')) || 512,
    });
}

export async function pintarQrs() {
    const lienzos = document.querySelectorAll('canvas[data-qr]');
    if (!lienzos.length) return;

    const { default: QRCode } = await import('qrcode');
    const opciones = { margin: 1, errorCorrectionLevel: 'M' };

    lienzos.forEach((lienzo) => pintarQr(QRCode, lienzo));

    // Descarga en alta resolución, para imprimirlo grande sin que se pixele.
    document.querySelectorAll('[data-qr-descargar]').forEach((boton) => {
        boton.addEventListener('click', async () => {
            const nombre = boton.dataset.qrDescargar;
            const lienzo = document.querySelector(`canvas[data-qr-nombre="${nombre}"]`);
            if (!lienzo) return;

            const enlace = document.createElement('a');
            enlace.href = await QRCode.toDataURL(lienzo.dataset.qr, { ...opciones, margin: 2, width: 1024 });
            enlace.download = `${nombre}.png`;
            enlace.click();
        });
    });
}

/* ------------------------------------------------------------------ */
/* Audio de las pantallas proyectadas                                  */
/* ------------------------------------------------------------------ */

/**
 * Reproduce en bucle el audio [data-proy-audio], si la pantalla tiene uno.
 * Los navegadores bloquean el autoplay con sonido hasta que alguien
 * interactúa con la página: si se bloquea, arranca con el primer clic o
 * tecla (por ejemplo, al poner la pantalla completa). Silenciarlo se recuerda
 * en este navegador para todas las pantallas. Devuelve el alternador, o null.
 * Lo usan las pantallas proyectadas y el centro de mando del celular.
 */
export function iniciarAudio(cuerpo = document.body) {
    const audio = cuerpo.querySelector('[data-proy-audio]');
    if (!audio) return null;

    const boton = cuerpo.querySelector('[data-proy-sonido]');
    const texto = cuerpo.querySelector('[data-proy-sonido-texto]');
    const CLAVE = 'proyeccion-silenciada';

    let silenciado = false;
    try {
        silenciado = localStorage.getItem(CLAVE) === '1';
    } catch {
        // Sin almacenamiento: arranca con sonido.
    }

    const pintar = () => {
        const sonando = !silenciado && !audio.paused;
        boton?.classList.toggle('proy-boton-silenciado', !sonando);
        boton?.querySelector('[data-sonido-on]')?.toggleAttribute('hidden', !sonando);
        boton?.querySelector('[data-sonido-off]')?.toggleAttribute('hidden', sonando);
        if (texto) texto.textContent = sonando ? 'Sonido' : 'Activar sonido';
    };

    const reproducir = () => {
        if (silenciado) return;
        audio.play().then(pintar).catch(pintar);
    };

    const alternar = () => {
        // Pausado por el navegador (no por el usuario): el clic solo lo arranca.
        silenciado = !silenciado && !audio.paused;
        try {
            localStorage.setItem(CLAVE, silenciado ? '1' : '0');
        } catch {
            // Sin almacenamiento: solo dura esta visita.
        }
        silenciado ? audio.pause() : reproducir();
        pintar();
    };

    audio.addEventListener('play', pintar);
    audio.addEventListener('pause', pintar);

    // Primer gesto del usuario: desbloquea el autoplay (salvo el clic del propio botón).
    const desbloquear = (e) => {
        if (e.target.closest?.('[data-proy-sonido]')) return;
        // La M fuera de un campo ya alterna el sonido.
        if (e.type === 'keydown' && /^m$/i.test(e.key) && !e.target.closest?.('input, textarea')) return;
        if (audio.paused) reproducir();
    };
    ['pointerdown', 'keydown', 'touchstart'].forEach((evento) =>
        document.addEventListener(evento, desbloquear, { passive: true })
    );

    boton?.addEventListener('click', alternar);
    reproducir();
    pintar();

    return alternar;
}

/* ------------------------------------------------------------------ */
/* Pantalla de proyección de una estación (dashboard)                  */
/* ------------------------------------------------------------------ */

export function iniciarProyeccion() {
    const cuerpo = document.querySelector('[data-proyeccion]');
    if (!cuerpo) return;

    const botonCompleta = cuerpo.querySelector('[data-proy-pantalla-completa]');
    const textoCompleta = cuerpo.querySelector('[data-proy-pantalla-completa-texto]');

    const alternarPantallaCompleta = () => {
        if (document.fullscreenElement) {
            document.exitFullscreen?.();
        } else {
            document.documentElement.requestFullscreen?.().catch(() => {});
        }
    };

    const alternarSonido = iniciarAudio(cuerpo);

    botonCompleta?.addEventListener('click', alternarPantallaCompleta);
    document.addEventListener('fullscreenchange', () => {
        if (textoCompleta) textoCompleta.textContent = document.fullscreenElement ? 'Salir' : 'Pantalla completa';
    });

    // F = pantalla completa · ← / → = misión anterior / siguiente
    document.addEventListener('keydown', (e) => {
        // En la consola se escribe el código: la F es una letra más.
        if (e.target.closest?.('input, textarea')) return;

        if (e.key === 'f' || e.key === 'F') alternarPantallaCompleta();
        if ((e.key === 'm' || e.key === 'M') && alternarSonido) alternarSonido();
        if (e.key === 'ArrowLeft' && cuerpo.dataset.anterior) window.location.href = cuerpo.dataset.anterior;
        if (e.key === 'ArrowRight' && cuerpo.dataset.siguiente) window.location.href = cuerpo.dataset.siguiente;
    });

    // Los controles y el cursor se ocultan si el mouse no se mueve.
    let espera;
    const despertar = () => {
        cuerpo.classList.remove('proy-inactiva');
        clearTimeout(espera);
        espera = setTimeout(() => cuerpo.classList.add('proy-inactiva'), 3000);
    };
    ['mousemove', 'mousedown', 'keydown', 'touchstart'].forEach((evento) =>
        document.addEventListener(evento, despertar, { passive: true })
    );
    despertar();

    // Si alguien regenera el QR desde el dashboard, el televisor lo actualiza solo.
    const lienzo = cuerpo.querySelector('canvas[data-qr]');
    const fuente = cuerpo.dataset.qrFuente;
    if (!lienzo || !fuente) return;

    setInterval(async () => {
        try {
            const respuesta = await fetch(fuente, { headers: { Accept: 'application/json' } });
            if (!respuesta.ok) return;

            const { qr } = await respuesta.json();
            if (!qr || qr === lienzo.dataset.qr) return;

            lienzo.dataset.qr = qr;
            const { default: QRCode } = await import('qrcode');
            pintarQr(QRCode, lienzo);
        } catch {
            // Sin red o sesión vencida: se conserva el QR actual.
        }
    }, 30000);
}
