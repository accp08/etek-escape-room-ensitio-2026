/* ------------------------------------------------------------------ */
/* Top 3 (/top): podio por dominio permitido con jugadores             */
/* ------------------------------------------------------------------ */

const ROTACION = 12000;     // ms por podio cuando hay varios dominios
const REFRESCO = 20000;     // ms entre consultas de los podios

export function iniciarTop() {
    const cuerpo = document.querySelector('[data-top]');
    if (!cuerpo) return;

    const contenedor = cuerpo.querySelector('[data-podios]');
    const navegacion = cuerpo.querySelector('[data-top-nav]');
    const puntos = cuerpo.querySelector('[data-puntos]');
    const diapositivas = () => [...contenedor.querySelectorAll('[data-slide]')];

    let actual = 0;
    let firma = contenedor.dataset.firma;
    let rotacion;

    // Rotación automática; cada cambio manual la reinicia para no saltar enseguida.
    const programar = () => {
        clearInterval(rotacion);
        rotacion = setInterval(() => {
            if (diapositivas().length > 1) mostrar(actual + 1);
        }, ROTACION);
    };

    const mostrar = (indice) => {
        const todas = diapositivas();
        if (!todas.length) return;

        actual = (indice + todas.length) % todas.length;
        todas.forEach((d, i) => d.toggleAttribute('data-activo', i === actual));
        [...puntos.children].forEach((p, i) => p.toggleAttribute('data-activo', i === actual));
    };

    const ir = (indice) => {
        mostrar(indice);
        programar();
    };

    // Un punto por podio: la cantidad cambia cuando un dominio recibe su primer jugador.
    const pintarPuntos = () => {
        const todas = diapositivas();
        navegacion.hidden = todas.length < 2;
        puntos.replaceChildren(...todas.map((d, i) => {
            const punto = document.createElement('button');
            punto.type = 'button';
            punto.title = d.dataset.slide ? `@${d.dataset.slide}` : 'Ranking general';
            punto.setAttribute('aria-label', punto.title);
            punto.addEventListener('click', () => ir(i));
            return punto;
        }));
    };

    cuerpo.querySelector('[data-top-anterior]')?.addEventListener('click', () => ir(actual - 1));
    cuerpo.querySelector('[data-top-siguiente]')?.addEventListener('click', () => ir(actual + 1));

    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') ir(actual - 1);
        if (e.key === 'ArrowRight') ir(actual + 1);
    });

    pintarPuntos();
    mostrar(0);
    programar();

    // Solo se repinta si el podio cambió (así no se repite la animación de entrada).
    setInterval(async () => {
        try {
            const respuesta = await fetch(cuerpo.dataset.top, { headers: { Accept: 'text/html' } });
            if (!respuesta.ok) return;

            const nueva = respuesta.headers.get('X-Firma');
            if (!nueva || nueva === firma) return;

            // Se conserva el dominio que se estaba viendo, aunque cambie su posición.
            const visible = diapositivas()[actual]?.dataset.slide;

            firma = nueva;
            contenedor.innerHTML = await respuesta.text();
            pintarPuntos();

            const indice = diapositivas().findIndex((d) => d.dataset.slide === visible);
            mostrar(indice === -1 ? 0 : indice);
        } catch {
            // Sin red: se conserva el podio actual.
        }
    }, REFRESCO);
}
