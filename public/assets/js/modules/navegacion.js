/**
 * Menú plegable del encabezado (mejora progresiva).
 * Sin JavaScript la navegación se muestra completa; con JavaScript, en pantallas
 * pequeñas se pliega detrás del botón "Menú" (aria-expanded / aria-controls).
 * En escritorio el CSS la mantiene siempre visible y oculta el botón.
 */
export function iniciarNavegacion(documento = document) {
    const boton = documento.querySelector('.boton-menu');
    const nav = documento.getElementById('menu-principal');
    if (!boton || !nav) {
        return;
    }

    const cerrar = () => {
        boton.setAttribute('aria-expanded', 'false');
        nav.classList.remove('navegacion--abierta');
    };

    const abrir = () => {
        boton.setAttribute('aria-expanded', 'true');
        nav.classList.add('navegacion--abierta');
    };

    nav.classList.add('navegacion--plegable');
    boton.hidden = false;

    boton.addEventListener('click', () => {
        if (boton.getAttribute('aria-expanded') === 'true') {
            cerrar();
        } else {
            abrir();
        }
    });

    // Al elegir una sección, el menú se cierra (el foco sigue el ancla).
    nav.addEventListener('click', (evento) => {
        if (evento.target instanceof Element && evento.target.closest('a')) {
            cerrar();
        }
    });

    // Escape cierra el menú y devuelve el foco al botón.
    documento.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape' && boton.getAttribute('aria-expanded') === 'true') {
            cerrar();
            boton.focus();
        }
    });
}
