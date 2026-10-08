/**
 * Editor de contenido enriquecido del dashboard.
 *
 * Es deliberadamente pequeño: el contenido de las misiones son párrafos con
 * negritas, cursivas y algún enlace, no documentos complejos. El HTML que
 * produce se vuelve a sanear en el servidor (App\Support\HtmlSeguro), así que
 * lo que se escriba aquí nunca llega crudo al juego.
 *
 * Marcado esperado:
 *   <div class="editor" data-editor [data-subir-imagen="URL"]>
 *       <textarea name="..." hidden data-editor-valor>HTML inicial</textarea>
 *   </div>
 *
 * Con data-subir-imagen el editor admite imágenes: se suben a esa URL y se
 * pueden ajustar de tamaño y alineación con la barra que aparece al tocarlas.
 */

const BOTONES = [
    { orden: 'bold',          etiqueta: 'B',  titulo: 'Negrita',   clase: 'font-black' },
    { orden: 'italic',        etiqueta: 'I',  titulo: 'Cursiva',   clase: 'italic' },
    { orden: 'underline',     etiqueta: 'U',  titulo: 'Subrayado', clase: 'underline' },
    { separador: true },
    { orden: 'insertUnorderedList', etiqueta: '• —', titulo: 'Lista' },
    { orden: 'enlace',        etiqueta: '🔗', titulo: 'Insertar enlace' },
    { orden: 'imagen',        etiqueta: '🖼', titulo: 'Insertar imagen', conImagenes: true },
    { orden: 'removeFormat',  etiqueta: '⌫',  titulo: 'Quitar formato' },
    { separador: true },
    { orden: 'fuente',        etiqueta: '&lt;/&gt;', titulo: 'Ver y editar el HTML' },
];

const ANCHOS = ['25', '50', '75', '100'];

const ALINEACIONES = [
    { valor: 'izquierda', etiqueta: '⇤', titulo: 'Alinear a la izquierda (el texto la rodea)' },
    { valor: 'centro',    etiqueta: '↔', titulo: 'Centrar' },
    { valor: 'derecha',   etiqueta: '⇥', titulo: 'Alinear a la derecha (el texto la rodea)' },
];

/**
 * Reduce la imagen antes de subirla: muchos servidores limitan la subida a
 * 2 MB y en el juego se ve en un celular, así que 1600 px de ancho bastan.
 * Los GIF se suben tal cual para no perder la animación.
 */
const ANCHO_MAXIMO = 1600;
const PESO_SIN_TOCAR = 600 * 1024;

async function optimizarImagen(archivo) {
    if (archivo.type === 'image/gif') return archivo;

    let mapa;
    try {
        mapa = await createImageBitmap(archivo);
    } catch {
        return archivo;   // formato que el navegador no sabe decodificar
    }

    if (mapa.width <= ANCHO_MAXIMO && archivo.size <= PESO_SIN_TOCAR) {
        mapa.close?.();

        return archivo;
    }

    const escala = Math.min(1, ANCHO_MAXIMO / mapa.width);
    const lienzo = document.createElement('canvas');
    lienzo.width = Math.round(mapa.width * escala);
    lienzo.height = Math.round(mapa.height * escala);
    lienzo.getContext('2d').drawImage(mapa, 0, 0, lienzo.width, lienzo.height);
    mapa.close?.();

    const blob = await new Promise((listo) => lienzo.toBlob(listo, 'image/webp', 0.85));

    // Sin soporte de WEBP toBlob devuelve PNG, que puede pesar más: se usa JPG.
    const final = blob?.type === 'image/webp'
        ? blob
        : await new Promise((listo) => lienzo.toBlob(listo, 'image/jpeg', 0.85));

    if (!final || final.size >= archivo.size) return archivo;

    const extension = final.type === 'image/webp' ? 'webp' : 'jpg';
    const nombre = (archivo.name || 'imagen').replace(/\.[^.]+$/, '') + '.' + extension;

    return new File([final], nombre, { type: final.type });
}

function construir(caja) {
    const campo = caja.querySelector('[data-editor-valor]');
    if (!campo) return;

    const marcador = caja.dataset.vacio || 'Escribe aquí…';
    const urlSubida = caja.dataset.subirImagen || '';

    // --- Estructura ---
    const barra = document.createElement('div');
    barra.className = 'editor-barra';

    const lienzo = document.createElement('div');
    lienzo.className = 'editor-lienzo';
    lienzo.contentEditable = 'true';
    lienzo.spellcheck = true;
    lienzo.dataset.vacio = marcador;
    lienzo.innerHTML = campo.value.trim() || '<p><br></p>';

    const fuente = document.createElement('textarea');
    fuente.className = 'editor-fuente';
    fuente.hidden = true;
    fuente.spellcheck = false;

    const pie = document.createElement('p');
    pie.className = 'editor-pie';
    const textoPie = urlSubida
        ? 'Se permiten negrita, cursiva, subrayado, listas, enlaces e imágenes (toca una imagen para ajustarla). El resto se descarta al guardar.'
        : 'Se permiten negrita, cursiva, subrayado, listas y enlaces. El resto se descarta al guardar.';
    pie.textContent = textoPie;

    // Barra contextual que aparece al seleccionar una imagen.
    const barraImagen = document.createElement('div');
    barraImagen.className = 'editor-barra editor-barra-imagen';
    barraImagen.hidden = true;

    caja.append(barra, barraImagen, lienzo, fuente, pie);

    // Firefox añade tiradores de tamaño que escriben estilos en línea: el
    // tamaño se controla desde la barra de la imagen.
    try { document.execCommand('enableObjectResizing', false, 'false'); } catch { /* no soportado */ }

    // --- Sincronización con el campo del formulario ---
    const sincronizar = () => {
        if (!fuente.hidden) {
            campo.value = fuente.value;

            return;
        }

        // La marca de selección de la imagen es solo visual.
        const copia = lienzo.cloneNode(true);
        copia.querySelectorAll('img.editor-img-activa').forEach((img) => img.classList.remove('editor-img-activa'));
        copia.querySelectorAll('img[class=""]').forEach((img) => img.removeAttribute('class'));
        campo.value = copia.innerHTML;
    };

    lienzo.addEventListener('input', sincronizar);
    fuente.addEventListener('input', sincronizar);
    caja.closest('form')?.addEventListener('submit', sincronizar);
    sincronizar();

    // --- Botones ---
    const refrescarEstados = () => {
        barra.querySelectorAll('[data-orden]').forEach((b) => {
            const orden = b.dataset.orden;
            if (['bold', 'italic', 'underline'].includes(orden)) {
                try {
                    b.setAttribute('aria-pressed', String(document.queryCommandState(orden)));
                } catch { /* algunos navegadores lo rechazan sin selección */ }
            }
        });
    };

    // Último punto del cursor dentro del lienzo: el diálogo de archivos le
    // quita el foco y la imagen debe insertarse donde estaba.
    let ultimoRango = null;

    document.addEventListener('selectionchange', () => {
        const seleccion = document.getSelection();
        if (!lienzo.contains(seleccion?.anchorNode)) return;
        if (seleccion.rangeCount) ultimoRango = seleccion.getRangeAt(0).cloneRange();
        refrescarEstados();
    });

    // --- Imágenes ---
    let imagenActiva = null;

    const selectorArchivo = document.createElement('input');
    selectorArchivo.type = 'file';
    selectorArchivo.accept = 'image/png,image/jpeg,image/webp,image/gif';
    selectorArchivo.hidden = true;
    caja.append(selectorArchivo);

    const avisar = (texto, error = false) => {
        pie.textContent = texto;
        pie.classList.toggle('editor-pie-error', error);
    };

    const restaurarRango = () => {
        if (!ultimoRango || !lienzo.contains(ultimoRango.startContainer)) return false;
        const seleccion = document.getSelection();
        seleccion.removeAllRanges();
        seleccion.addRange(ultimoRango);

        return true;
    };

    const insertarImagen = (url) => {
        lienzo.focus();
        const html = `<img src="${url.replace(/"/g, '&quot;')}" alt="" data-ancho="100" data-alinear="centro">`;

        if (restaurarRango()) {
            document.execCommand('insertHTML', false, html);
        } else {
            lienzo.insertAdjacentHTML('beforeend', `<p>${html}</p>`);
        }

        sincronizar();
    };

    const subirImagen = async (archivo) => {
        if (!urlSubida || !archivo || !archivo.type.startsWith('image/')) return;

        const token = caja.closest('form')?.querySelector('input[name="_token"]')?.value
            || document.querySelector('meta[name="csrf-token"]')?.content || '';

        avisar('Preparando imagen…');
        const optimizada = await optimizarImagen(archivo);

        const datos = new FormData();
        datos.append('imagen', optimizada);

        avisar('Subiendo imagen…');

        try {
            const respuesta = await fetch(urlSubida, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                body: datos,
                credentials: 'same-origin',
            });
            const cuerpo = await respuesta.json().catch(() => ({}));

            if (!respuesta.ok || !cuerpo.url) {
                avisar(cuerpo.errors?.imagen?.[0] || cuerpo.message || 'No se pudo subir la imagen.', true);

                return;
            }

            insertarImagen(cuerpo.url);
            avisar(textoPie);
        } catch {
            avisar('No se pudo subir la imagen. Revisa la conexión.', true);
        }
    };

    selectorArchivo.addEventListener('change', () => {
        subirImagen(selectorArchivo.files?.[0]);
        selectorArchivo.value = '';
    });

    const refrescarBarraImagen = () => {
        barraImagen.hidden = !imagenActiva;
        if (!imagenActiva) return;

        barraImagen.querySelectorAll('[data-ancho]').forEach((b) => {
            b.setAttribute('aria-pressed', String(b.dataset.ancho === (imagenActiva.dataset.ancho || '100')));
        });
        barraImagen.querySelectorAll('[data-alinear]').forEach((b) => {
            b.setAttribute('aria-pressed', String(b.dataset.alinear === (imagenActiva.dataset.alinear || 'centro')));
        });
    };

    const activarImagen = (img) => {
        imagenActiva?.classList.remove('editor-img-activa');
        imagenActiva = img && lienzo.contains(img) ? img : null;
        imagenActiva?.classList.add('editor-img-activa');
        refrescarBarraImagen();
    };

    const botonImagen = (etiqueta, titulo, accion, datos = {}) => {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'editor-boton';
        boton.innerHTML = etiqueta;
        boton.title = titulo;
        boton.setAttribute('aria-label', titulo);
        Object.assign(boton.dataset, datos);
        // mousedown evita que el lienzo pierda la imagen seleccionada.
        boton.addEventListener('mousedown', (e) => e.preventDefault());
        boton.addEventListener('click', () => {
            if (!imagenActiva) return;
            accion();
            sincronizar();
            refrescarBarraImagen();
        });
        barraImagen.append(boton);
    };

    const rotulo = (texto) => {
        const span = document.createElement('span');
        span.className = 'editor-rotulo';
        span.textContent = texto;
        barraImagen.append(span);
    };

    const separador = (destino) => {
        const sep = document.createElement('span');
        sep.className = 'editor-separador';
        destino.append(sep);
    };

    if (urlSubida) {
        rotulo('Tamaño');
        ANCHOS.forEach((ancho) => botonImagen(`${ancho}%`, `Ancho ${ancho}%`, () => {
            imagenActiva.dataset.ancho = ancho;
        }, { ancho }));

        separador(barraImagen);
        rotulo('Posición');
        ALINEACIONES.forEach((a) => botonImagen(a.etiqueta, a.titulo, () => {
            imagenActiva.dataset.alinear = a.valor;
        }, { alinear: a.valor }));

        separador(barraImagen);
        botonImagen('Alt', 'Texto alternativo (descripción de la imagen)', () => {
            const alt = prompt('Describe la imagen (texto alternativo)', imagenActiva.alt || '');
            if (alt !== null) imagenActiva.alt = alt.trim();
        });
        botonImagen('🗑', 'Quitar imagen', () => {
            imagenActiva.remove();
            imagenActiva = null;
        });

        lienzo.addEventListener('click', (e) => {
            activarImagen(e.target instanceof HTMLImageElement ? e.target : null);
        });

        lienzo.addEventListener('keydown', (e) => {
            if (imagenActiva && (e.key === 'Delete' || e.key === 'Backspace')) {
                e.preventDefault();
                imagenActiva.remove();
                activarImagen(null);
                sincronizar();
            } else if (imagenActiva) {
                activarImagen(null);
            }
        });

        // Arrastrar un archivo de imagen al lienzo lo sube en ese punto.
        lienzo.addEventListener('drop', (e) => {
            const archivo = [...(e.dataTransfer?.files || [])].find((f) => f.type.startsWith('image/'));
            if (!archivo) return;

            e.preventDefault();
            const rango = document.caretRangeFromPoint?.(e.clientX, e.clientY);
            if (rango) ultimoRango = rango;
            subirImagen(archivo);
        });
    }

    const aplicar = (orden) => {
        if (orden === 'fuente') {
            const aCodigo = fuente.hidden;

            if (aCodigo) {
                fuente.value = formatear(lienzo.innerHTML);
            } else {
                lienzo.innerHTML = fuente.value.trim() || '<p><br></p>';
            }

            activarImagen(null);
            fuente.hidden = !aCodigo;
            lienzo.hidden = aCodigo;
            barra.querySelector('[data-orden="fuente"]').setAttribute('aria-pressed', String(aCodigo));
            sincronizar();

            return;
        }

        if (fuente.hidden === false) return;   // en modo código no aplican los formatos

        lienzo.focus();

        if (orden === 'imagen') {
            selectorArchivo.click();

            return;
        }

        if (orden === 'enlace') {
            const url = prompt('Dirección del enlace (https://…)');
            if (url) document.execCommand('createLink', false, url.trim());
        } else {
            // execCommand está obsoleto pero es lo único con soporte universal
            // para edición enriquecida sin arrastrar una librería entera.
            document.execCommand(orden, false, null);
        }

        sincronizar();
        refrescarEstados();
    };

    BOTONES.forEach((def) => {
        if (def.conImagenes && !urlSubida) return;

        if (def.separador) {
            separador(barra);

            return;
        }

        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'editor-boton ' + (def.clase || '');
        boton.innerHTML = def.etiqueta;
        boton.title = def.titulo;
        boton.setAttribute('aria-label', def.titulo);
        boton.dataset.orden = def.orden;
        boton.addEventListener('click', () => aplicar(def.orden));
        barra.append(boton);
    });

    // Pegar siempre como texto plano: evita arrastrar estilos de Word o del
    // navegador. Una imagen pegada (captura de pantalla) se sube.
    lienzo.addEventListener('paste', (e) => {
        e.preventDefault();
        const portapapeles = e.clipboardData || window.clipboardData;
        const archivo = [...(portapapeles.files || [])].find((f) => f.type.startsWith('image/'));

        if (archivo && urlSubida) {
            subirImagen(archivo);

            return;
        }

        const texto = portapapeles.getData('text/plain');
        document.execCommand('insertText', false, texto);
    });
}

/** Indentación legible para la vista de código. */
function formatear(html) {
    return html
        .replace(/></g, '>\n<')
        .replace(/<\/p>\s*<p>/g, '</p>\n\n<p>')
        .trim();
}

export function iniciarEditores(raiz = document) {
    raiz.querySelectorAll('[data-editor]').forEach((caja) => {
        if (caja.dataset.listo === 'si') return;
        caja.dataset.listo = 'si';
        construir(caja);
    });
}
