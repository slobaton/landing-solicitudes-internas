/**
 * Arranque de la landing. Marca que hay JavaScript e inicia los módulos.
 * Módulos: navegación, cifras en vivo, acceso, registrar solicitud y mis solicitudes.
 */
import { iniciarNavegacion } from './modules/navegacion.js';
import { iniciarEstadisticas } from './modules/estadisticas.js';
import { iniciarAcceso } from './modules/acceso.js';
import { iniciarRegistro } from './modules/registro-solicitud.js';
import { iniciarMisSolicitudes } from './modules/mis-solicitudes.js';

document.documentElement.classList.add('js');
iniciarNavegacion();

const estadisticas = iniciarEstadisticas();
const acceso = iniciarAcceso();
iniciarRegistro(); // registro y mis solicitudes escuchan 'sesion:cambio':
iniciarMisSolicitudes(); // deben iniciarse antes de comprobar la sesión

// Registrar o cancelar cambia las cifras públicas.
document.addEventListener('solicitud:registrada', () => estadisticas.cargar());
document.addEventListener('solicitud:cancelada', () => estadisticas.cargar());

// Cifras (público) y sesión (/auth/me) se consultan en paralelo.
estadisticas.cargar();
acceso.comprobarSesion();
