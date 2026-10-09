# Sistema de seguimiento de solicitudes internas

Trabajo Final — Módulo 3, *Desarrollo al lado del cliente y servidor web*.

Aplicación web que centraliza las solicitudes entre áreas de una institución. Reemplaza los
pedidos por correo, WhatsApp, teléfono o en persona por un canal único: cada solicitud recibe un
número (`AAAA-NNNNN`), una fecha límite calculada en horas laborales y un historial de cada cambio.

La aplicación es una **landing page** (`/`) que presenta el sistema y permite usarlo sin salir de
la página, sobre una **API REST JSON** (`/api/v1`) en PHP y una base de datos **MySQL 8**.

## Funcionalidades

| Sección de la landing | Qué hace | Requisito |
|---|---|---|
| Inicio, El problema, Cómo funciona, Beneficios | Explican el propósito, el flujo (Registrar → Asignar → Atender → Cerrar) y los plazos por prioridad | Comunica el propósito |
| Cifras en vivo | Totales de solicitudes por estado leídos de la BD (sin datos personales) | Fuente de datos |
| Acceso | Iniciar y cerrar sesión (las cuentas las crea el administrador; no hay registro público) | RF-01 |
| Registrar solicitud | Formulario con áreas, tipos y prioridades activos; muestra número y fecha límite | RF-02, RF-03 |
| Mis solicitudes | Lista paginada con estado y fecha límite, detalle con historial y cancelación con motivo | RF-09, RF-14 |

Las solicitudes **no se borran**: cancelar cambia el estado a *Cancelada* y guarda el motivo en
el historial. Fuera de alcance en esta fase: asignación, comentarios, adjuntos, notificaciones,
reportes, edición desde la landing, registro de cuentas y recuperación de contraseña.

## Tecnologías

- **Backend:** PHP 8.4 puro (sin framework), arquitectura MVC, PDO.
- **Base de datos:** MySQL 8.0 / InnoDB (`sis_solicitudes`), con triggers de integridad y auditoría.
- **Frontend:** vistas PHP + HTML5 semántico + CSS3 puro + JavaScript ES6 (módulos nativos y `fetch`).
  Sin React, Vue, Angular, frameworks CSS ni proceso de compilación; sin CDNs ni fuentes externas.
- **Comunicación cliente-servidor:** API REST JSON con sesión PHP (cookie `HttpOnly`, `SameSite=Lax`)
  y token CSRF en la cabecera `X-CSRF-Token` para POST/PUT/DELETE.

## Arquitectura

```text
Navegador ── GET / ──────────────► public/index.php (front controller)
   │                                   ├─ routes/web.php → Controllers/Web/LandingController → Views (HTML)
   └─ fetch /api/v1/... (JSON) ────►   └─ routes/api.php → Middleware (Auth, CSRF)
                                             → Controllers/Api → Services (reglas) → Repositories (PDO) → MySQL
```

| Capa | Carpeta | Responsabilidad |
|---|---|---|
| Front controller | `public/index.php` | Arranque, carga de rutas y despacho |
| Rutas | `routes/api.php`, `routes/web.php` | Método + ruta → controlador y middleware |
| Middleware | `app/Middleware/` | Sesión obligatoria (`AuthMiddleware`) y CSRF (`CsrfMiddleware`) |
| Controladores | `app/Controllers/Api/`, `app/Controllers/Web/` | Traducen petición ↔ respuesta; sin SQL |
| Validadores | `app/Validators/` | Reglas de entrada (422 con errores por campo) |
| Servicios | `app/Services/` | Reglas de negocio (fecha límite, transiciones, login con límite de intentos) |
| Repositorios | `app/Repositories/` | Único lugar con SQL (sentencias preparadas) |
| Núcleo | `app/Core/` | Router, Request, Response (JSON/HTML), sesión, BD, errores, vistas |
| Vistas | `app/Views/` | Layout, parciales y una vista por sección; toda salida escapada |
| Estilos | `public/assets/css/` | `variables`, `base`, `layout`, `components`, `forms`, `responsive` (móvil primero) |
| JavaScript | `public/assets/js/api/` | Solo comunicación HTTP (`http.js` centraliza `fetch`, CSRF en memoria y errores) |
| | `public/assets/js/modules/` | Comportamiento de cada sección (estados loading/empty/success/error/unauthorized) |
| | `public/assets/js/app.js` | Arranque: inicia los módulos y comprueba la sesión (`/auth/me`) |

### Endpoints

| Método y ruta | Acceso | Uso |
|---|---|---|
| `GET /api/v1/health` | Público | Estado de la API y la BD |
| `GET /api/v1/estadisticas` | Público | Conteos por estado (solo totales) |
| `POST /api/v1/auth/login` | Público | Iniciar sesión; devuelve usuario y `csrf_token` |
| `POST /api/v1/auth/logout` | Sesión + CSRF | Cerrar sesión |
| `GET /api/v1/auth/me` | Sesión | Usuario actual y `csrf_token` vigente |
| `GET /api/v1/catalogos` | Sesión | Áreas, tipos de solicitud y prioridades activos |
| `POST /api/v1/solicitudes` | Sesión + CSRF | Registrar (201 con número y fecha límite) |
| `GET /api/v1/solicitudes` | Sesión | Mis solicitudes (paginado: `pagina`, `por_pagina`) |
| `GET /api/v1/solicitudes/{id}` | Sesión | Detalle con historial (solo el solicitante) |
| `PUT /api/v1/solicitudes/{id}` | Sesión + CSRF | Editar en estado Registrada (no se usa en la landing) |
| `DELETE /api/v1/solicitudes/{id}` | Sesión + CSRF | **Cancelar** con `motivo` y `version` (no borra) |

Contrato completo: [`docs/openapi.yaml`](docs/openapi.yaml) (OpenAPI 3.1) y
[`docs/contrato_solicitudes.md`](docs/contrato_solicitudes.md). Diseño de la landing:
[`docs/diseno_landing.md`](docs/diseno_landing.md).

## Requisitos

- PHP 8.4 con extensiones `pdo_mysql`, `mbstring`, `json` y `curl` (esta última solo para las pruebas).
- MySQL 8.0 (en la Mac de desarrollo: DBngin 8.0.33).
- Un navegador actual (Chrome, Safari, Firefox o Edge).

## Instalación

1. **Base de datos.** Ejecutar los scripts `database/V001` a `V011` en orden,
   siguiendo `database/plan_migraciones.md` §3. Algunos scripts tienen
   marcadores que se sustituyen en memoria desde `.env`; no se ejecutan directamente.
2. **Configuración.** Copiar el ejemplo y completar los valores locales:

   ```bash
   cd <carpeta del repositorio>
   cp .env.example .env
   ```

   `.env` contiene la conexión (`DB_*`), la zona horaria (`APP_TIMEZONE`), el entorno (`APP_ENV`)
   y los usuarios de demostración (`DEMO_*`). **No se versiona** (está en `.gitignore`) y este
   README no incluye ningún valor: las credenciales solo existen en `.env`.
3. **Usuarios de demostración** (un solicitante y un jefe de área):

   ```bash
   php tools/crear_usuarios_demo.php
   ```

## Ejecución

Desde la carpeta del repositorio (con MySQL encendido):

```bash
php -S 127.0.0.1:8080 -t public
```

En la Mac de desarrollo el binario es `"/Users/slobaton/Library/Application Support/Herd/bin/php"`.

- Landing: <http://127.0.0.1:8080/>
- Salud de la API: <http://127.0.0.1:8080/api/v1/health>

Para entrar, usar el usuario y la contraseña de demostración definidos en `.env`
(`DEMO_SOLICITANTE_USUARIO` / `DEMO_SOLICITANTE_PASSWORD`).

## Pruebas

Con el servidor en marcha:

```bash
php tests/api_test.php            # por defecto http://127.0.0.1:8080
```

La suite recorre todos los endpoints (incluidos `/estadisticas` y `/catalogos`), verifica que cada
ruta esté documentada en `docs/openapi.yaml` y guarda el resultado en `tests/resultados/`.
Lee las credenciales de `.env` sin imprimirlas. Cada ejecución crea **una** solicitud de prueba,
siempre a través de la API, que termina cancelada (detalle en la cabecera de `tests/api_test.php`).

## Diseño, adaptabilidad y accesibilidad

- **Responsive (móvil primero):** cortes en 600, 768, 900 y 1200 px; revisado en 320, 360, 768 y
  1280 px sin desplazamiento horizontal. En móvil, la navegación se pliega en un botón "Menú" y
  la tabla de *Mis solicitudes* se muestra como tarjetas.
- **Contraste WCAG 2.1 AA:** todas las combinaciones de texto verificadas (mínimo 4.5:1; la paleta
  y sus valores están en `public/assets/css/variables.css` y `docs/diseno_landing.md` §4).
- **Teclado:** enlace "Saltar al contenido", foco visible en todos los controles, orden de
  tabulación igual al visual, menú y confirmación de cancelar cerrables con Escape (el foco
  vuelve al botón), sin trampas de foco ni ventanas modales.
- **Lectores de pantalla:** HTML semántico (un `h1`, secciones con `aria-labelledby`), etiquetas en
  todos los campos, errores enlazados con `aria-describedby` y `aria-invalid`, mensajes con
  `role="status"` / `role="alert"`, y el estado nunca se comunica solo con color.
- **Movimiento:** el desplazamiento suave se desactiva con `prefers-reduced-motion`.

## Seguridad

- Toda salida PHP se escapa con `htmlspecialchars`; en JavaScript los datos se insertan con
  `textContent` (nunca `innerHTML`).
- El token CSRF vive solo en memoria (variable de módulo); nunca en `localStorage` ni `sessionStorage`.
- Cabeceras de la página: `Content-Security-Policy` (solo mismo origen, sin scripts ni estilos en
  línea), `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, `X-Content-Type-Options: nosniff`.
- `/estadisticas` es público pero devuelve solo totales globales (sin títulos, personas ni áreas).
- La validación en JavaScript es solo de ayuda; el backend valida todo de nuevo.
- Errores de la API sin trazas, SQL ni mensajes del motor (el detalle va a `storage/logs/app.log`).

## Estructura

```text
<repositorio>/
├── app/            Controllers (Api, Web), Core, Middleware, Models, Repositories, Services, Validators, Views
├── bootstrap/      Arranque (autocarga, .env, zona horaria, errores)
├── config/         app.php, database.php, session.php
├── database/       scripts SQL V001–V011, reversión (rollback/), verificación y plan de migraciones
├── docs/           openapi.yaml, contrato_solicitudes.md, diseno_landing.md
├── public/         index.php y assets (css, img, js/api, js/modules)
├── routes/         api.php, web.php
├── storage/        sesiones, límite de intentos y logs (no versionados)
├── tests/          api_test.php y resultados/
└── tools/          crear_usuarios_demo.php, demo_api.sh
```
