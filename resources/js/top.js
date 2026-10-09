/* ------------------------------------------------------------------ */
/* Top 3 (/top): podio por dominio permitido                           */
/* ------------------------------------------------------------------ */

const ROTACION = 12000;     // ms por podio cuando hay varios dominios
const REFRESCO = 20000;     // ms entre consultas de los podios

export function iniciarTop() {
    const cuerpo = document.querySelector('[data-top]');
    if (!cuerpo) return;

    const contenedor = cuerpo.querySelector('[data-podios]');
    const puntos = () => [...cuerpo.querySelectorAll('[data-puntos] > span')];
    const diapositivas = () => [...contenedor.querySelectorAll('[data-slide]')];

    let actual = 0;
    let firma = contenedor.dataset.firma;

    const mostrar = (indice) => {
        const todas = diapositivas();
        if (!todas.length) return;

        actual = indice % todas.length;
        todas.forEach((d, i) => d.toggleAttribute('data-activo', i === actual));
        puntos().forEach((p, i) => p.toggleAttribute('data-activo', i === actual));
    };

    setInterval(() => {
        if (diapositivas().length > 1) mostrar(actual + 1);
    }, ROTACION);

    // Solo se repinta si el podio cambió (así no se repite la animación de entrada).
    setInterval(async () => {
        try {
            const respuesta = await fetch(cuerpo.dataset.top, { headers: { Accept: 'text/html' } });
            if (!respuesta.ok) return;

            const nueva = respuesta.headers.get('X-Firma');
            if (!nueva || nueva === firma) return;

            firma = nueva;
            contenedor.innerHTML = await respuesta.text();
            mostrar(actual);
        } catch {
            // Sin red: se conserva el podio actual.
        }
    }, REFRESCO);
}
