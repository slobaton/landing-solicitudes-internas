# Diseño de la landing page — Sistema de seguimiento de solicitudes internas

**Checkpoint:** CP-FRONT-01 — Diseño de la landing (sin programar)
**Fecha:** 2026-10-08
**Workflow:** `.agents/workflows/05_frontend_workflow.md` (Trabajo Final)
**Skills aplicados:** `frontend-design`, `frontend-engineering`, `api-design-principles`, `php-development`
(se adelantan criterios de `responsive-design` y `accessibility-compliance`; su revisión formal es CP-FRONT-06)
**Fuentes:** `04_decisiones/decisiones_frontend.md`, `04_decisiones/decisiones_api.md`,
`.agents/guides/estructura_frontend.md`, `06_codigo/docs/contrato_solicitudes.md`,
`database/V003` (tablas), `V008` (permisos), `V009` y `V011` (catálogos).

Este documento no contiene código de la aplicación. Define qué se construirá en CP-FRONT-02..06.

---

## 1. Público objetivo y tarea principal

| Público | Qué necesita de la página | Tarea principal |
|---|---|---|
| Funcionarios solicitantes (mayoría) | Entender que existe un canal único y usarlo sin capacitación | Iniciar sesión → registrar una solicitud → ver en qué estado está |
| Jefes de área | Confiar en que las solicitudes llegan ordenadas y con plazo | Conocer el flujo y las cifras generales (su panel está fuera de alcance) |
| Visitante sin sesión (cualquier funcionario) | Saber qué es el sistema y cómo acceder | Leer y pulsar "Iniciar sesión" |

Contexto de uso: computadoras de oficina (escritorio, 1280 px o más) y celulares personales
(360 px). Usuarios no técnicos; textos cortos y en español claro.

**Modo de diseño (skill `frontend-design`):** primario *marketing/institucional* para las
secciones informativas; secundario *producto/espacio de trabajo* para Acceso, Registrar
solicitud y Mis solicitudes (formularios y listas sobrias, sin decoración).

## 2. Mensaje

- **Tesis visual (una frase):** una página institucional sobria, azul y blanca, que pasa
  de "esto es lo que resuelve" a "hágalo ahora" sin salir de la misma página.
- **Titular:** "Sus solicitudes internas, en un solo lugar y con seguimiento."
- **Subtítulo:** "Registre su pedido a cualquier área, reciba un número y sepa en todo
  momento en qué estado está y cuál es su fecha límite de atención."
- **Llamadas a la acción:** "Registrar solicitud" (primaria) y "Cómo funciona" (secundaria).

## 3. Secciones y contenido

Una sola página `/`. Cada sección es un `<section>` con `id` (ancla) y su propio `<h2>`;
el hero lleva el único `<h1>`. Una vista parcial por sección en
`app/Views/landing/secciones/`.

| # | Sección (`id`) | Contenido | Datos | RF / regla |
|---|---|---|---|---|
| 1 | Inicio (`inicio`) | `<h1>` titular, subtítulo, botones "Registrar solicitud" (ancla a `#registrar`, o a `#acceso` si no hay sesión) y "Cómo funciona" (ancla a `#como-funciona`) | Estático | Comunica propósito |
| 2 | El problema (`problema`) | Párrafo breve + 4 tarjetas: "Se pierden en el correo", "Se olvidan en WhatsApp", "Llamadas sin registro", "Pedidos en persona sin plazo". Cierre: "Nadie sabe en qué estado está su pedido" | Estático | Contexto del caso |
| 3 | Cómo funciona (`como-funciona`) | Lista ordenada (`<ol>`) de 4 pasos: **Registrar** (usted, recibe número AAAA-NNNNN y fecha límite) → **Asignar** (el jefe del área elige responsable) → **Atender** (el responsable trabaja: En proceso → Atendida) → **Cerrar** (usted confirma o se cierra automáticamente). Nota: "Puede cancelar mientras esté Registrada o Asignada, indicando el motivo." Tabla pequeña de plazos por prioridad: Baja 40 h, Media 24 h, Alta 8 h, Urgente 4 h (horas laborales) | Estático (valores de `V009`) | RF-02, RF-03, RF-14, RN-D07 |
| 4 | Beneficios (`beneficios`) | 4 ítems: número único y trazabilidad; fecha límite calculada automáticamente; historial de cada cambio; un solo canal para todas las áreas | Estático | Comunica propósito |
| 5 | Cifras en vivo (`cifras`) | 4 indicadores: Total registradas, Pendientes de asignar, En proceso, Atendidas o cerradas. Texto "Actualizado: HH:MM" y botón "Actualizar" | `GET /api/v1/estadisticas` (público) | Fuente de datos BD |
| 6 | Acceso (`acceso`) | Sin sesión: formulario usuario + contraseña, botón "Iniciar sesión", nota "Las cuentas las crea el administrador; no hay registro público". Con sesión: "Hola, {nombres} ({área})" y botón "Cerrar sesión" | `POST /auth/login`, `GET /auth/me`, `POST /auth/logout` | RF-01 |
| 7 | Registrar solicitud (`registrar`) | Formulario: título (máx. 150), descripción (máx. 5000, con contador), área destino (select), tipo de solicitud (select con descripción), prioridad (select con "N horas laborales"). Botón "Registrar". Éxito: "Solicitud **2026-00042** registrada. Fecha límite: 13/10/2026 09:20" | `GET /api/v1/catalogos` + `POST /api/v1/solicitudes` | RF-02, RF-03 |
| 8 | Mis solicitudes (`mis-solicitudes`) | Lista: número, título, estado (insignia), prioridad, fecha de registro, fecha límite (marca "Vencida" si `vencida = true`). Paginación "Anterior / Siguiente". Al pulsar "Ver detalle": descripción, responsable, historial (lista cronológica) y, si `acciones.puede_cancelar`, formulario "Cancelar solicitud" con motivo obligatorio (máx. 500) y confirmación | `GET /api/v1/solicitudes`, `GET /api/v1/solicitudes/{id}`, `DELETE /api/v1/solicitudes/{id}` | RF-09, RF-14 |
| 9 | Pie (`footer`) | Nombre del sistema, institución, "Trabajo Final — Módulo 3", enlace a `/api/v1/health` como "Estado del servicio" | Estático | — |

Las secciones 7 y 8 se muestran siempre en el HTML, pero su contenido depende de la sesión
(ver §7, estado *unauthorized*). La edición (`PUT`) existe en la API, pero no está entre las
secciones aprobadas de la landing y no se incluye.

**Encabezado (`header`):** nombre del sistema a la izquierda; navegación con anclas
(Cómo funciona, Cifras, Registrar, Mis solicitudes) y botón "Iniciar sesión" / "Salir".
En móvil la navegación se pliega en un botón "Menú" (`<button aria-expanded>`).
Primer elemento enfocable: enlace "Saltar al contenido".

## 4. Paleta

Contrastes calculados con la fórmula de luminancia relativa de WCAG 2.1 (objetivo AA:
4.5:1 texto normal, 3:1 texto grande y componentes).

| Variable CSS | Valor | Uso | Contraste verificado |
|---|---|---|---|
| `--color-texto` | `#1B2A41` | Texto principal | 14.44:1 sobre blanco; 13.34:1 sobre `--color-fondo-alt` |
| `--color-texto-suave` | `#4A5568` | Texto secundario, ayudas | 7.53:1 sobre blanco; 6.95:1 sobre fondo alterno |
| `--color-primario` | `#1F4E8C` | Botón primario, enlaces, encabezados de sección | 8.31:1 (blanco sobre él y él sobre blanco) |
| `--color-primario-oscuro` | `#163A6B` | Hover/activo del botón primario | 11.34:1 con blanco |
| `--color-acento` | `#0F766E` | Iconos y pasos de "Cómo funciona" | 5.47:1 sobre blanco |
| `--color-fondo` | `#FFFFFF` | Fondo general | — |
| `--color-fondo-alt` | `#F4F6F9` | Secciones alternas | — |
| `--color-hero` | `#1B2A41` | Fondo del hero y del pie | Blanco 14.44:1; `#C9D6EA` 9.83:1 |
| `--color-foco` | `#F2A900` | Anillo de foco (`:focus-visible`, 3 px) | 7.19:1 sobre `#1B2A41`; se combina con contorno oscuro sobre fondos claros |
| `--color-exito` | `#067647` | Mensajes de éxito, estados CERRADA/ATENDIDA | 5.69:1 |
| `--color-error` | `#B42318` | Errores de campo y de sección, "Vencida" | 6.57:1 |
| `--color-aviso` | `#B54708` | Avisos, estado REGISTRADA pendiente | 5.43:1 |

**Insignias de estado:** texto `--color-texto` sobre un tono claro del color de estado, más
el nombre del estado escrito (el color nunca es la única señal). REGISTRADA (aviso),
ASIGNADA y EN_PROCESO (primario), ATENDIDA y CERRADA (éxito), RECHAZADA y CANCELADA (gris
`#4A5568`).

## 5. Tipografía y espaciado

- Familia: pila del sistema, sin descargas:
  `system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`.
  Números (cifras, número de solicitud): `font-variant-numeric: tabular-nums`.
- Escala (rem, base 16 px, `clamp` para títulos): `h1` 2–2.75 rem, `h2` 1.5–2 rem,
  `h3` 1.125–1.25 rem, cuerpo 1 rem (línea 1.6), ayudas 0.875 rem. Cifras en vivo 2.5 rem.
- Ancho de lectura máximo 70 caracteres; contenedor máximo 1120 px.
- Espaciado en múltiplos de 0.25 rem (`--esp-1` … `--esp-8`); separación vertical entre
  secciones 4 rem (escritorio) / 2.5 rem (móvil).
- Bordes `--radio: 8px`; sombras mínimas solo en tarjetas de cifras y formularios.
- Íconos: SVG en línea decorativos (`aria-hidden="true"`), sin librerías externas.
- Movimiento: solo desplazamiento suave a anclas, desactivado con
  `prefers-reduced-motion: reduce`.

## 6. Wireframes en texto

### 6.1 Móvil (360 px, una columna)

```text
┌──────────────────────────────┐
│ [Saltar al contenido]        │ (visible solo con foco)
│ SIS Solicitudes      [Menú ▾]│ header fijo arriba
├──────────────────────────────┤
│ ███ HERO (fondo oscuro) ███  │
│ Sus solicitudes internas,    │ h1
│ en un solo lugar y con       │
│ seguimiento.                 │
│ Registre su pedido…          │ subtítulo
│ [ Registrar solicitud     ]  │ botón ancho completo
│ [ Cómo funciona           ]  │ botón secundario
├──────────────────────────────┤
│ El problema                  │ h2
│ ┌──────────────────────────┐ │ 4 tarjetas apiladas
│ │ ✉ Se pierden en el correo│ │
│ └──────────────────────────┘ │
│ …                            │
├──────────────────────────────┤
│ Cómo funciona                │
│ ① Registrar                  │ lista vertical numerada
│ │                            │
│ ② Asignar                    │
│ ③ Atender                    │
│ ④ Cerrar                     │
│ Plazos: tabla 2 columnas     │
├──────────────────────────────┤
│ Beneficios (4 ítems apilados)│
├──────────────────────────────┤
│ Cifras en vivo               │
│ ┌───────────┐ ┌───────────┐  │ rejilla 2×2
│ │  128      │ │  14       │  │
│ │ Total     │ │ Pendientes│  │
│ └───────────┘ └───────────┘  │
│ ┌───────────┐ ┌───────────┐  │
│ │  23       │ │  91       │  │
│ └───────────┘ └───────────┘  │
│ Actualizado 10:42 [Actualizar]│
├──────────────────────────────┤
│ Acceso                       │
│ Usuario    [______________]  │
│ Contraseña [______________]  │
│ [ Iniciar sesión          ]  │
├──────────────────────────────┤
│ Registrar solicitud          │
│ (sin sesión: aviso + botón   │
│  "Iniciar sesión")           │
│ Título       [___________]   │ campos apilados, etiqueta
│ Descripción  [___________]   │ arriba, error debajo
│              0/5000          │
│ Área         [▾]             │
│ Tipo         [▾]             │
│ Prioridad    [▾]             │
│ [ Registrar               ]  │
├──────────────────────────────┤
│ Mis solicitudes              │
│ ┌──────────────────────────┐ │ tarjeta por solicitud
│ │ 2026-00041  [Registrada] │ │ (no tabla en móvil)
│ │ Instalar impresora…      │ │
│ │ Límite 12/10 09:20       │ │
│ │ [Ver detalle]            │ │
│ └──────────────────────────┘ │
│ [◀ Anterior] 1/2 [Siguiente▶]│
│ Detalle: se despliega debajo │
│ de la tarjeta (historial +   │
│ cancelar con motivo)         │
├──────────────────────────────┤
│ Pie (fondo oscuro)           │
└──────────────────────────────┘
```

### 6.2 Tableta (768 px)

Navegación visible en una línea (sin botón "Menú"); tarjetas de El problema y Beneficios
en 2 columnas; Cómo funciona en 2×2; cifras en 4 columnas; formularios en una columna con
ancho máximo de 640 px; Mis solicitudes sigue en tarjetas.

### 6.3 Escritorio (1280 px, contenedor 1120 px)

```text
┌──────────────────────────────────────────────────────────────────────┐
│ SIS Solicitudes   Cómo funciona  Cifras  Registrar  Mis solicitudes [Iniciar sesión] │
├──────────────────────────────────────────────────────────────────────┤
│ ███████████████████████████ HERO ████████████████████████████████████ │
│ Sus solicitudes internas, en un        │  ┌──────────────────────┐   │
│ solo lugar y con seguimiento.          │  │ Ilustración SVG:     │   │
│ Registre su pedido…                    │  │ tarjeta de solicitud │   │
│ [Registrar solicitud] [Cómo funciona]  │  │ 2026-00041 · Media   │   │
│                                        │  └──────────────────────┘   │
├──────────────────────────────────────────────────────────────────────┤
│ El problema:  [Correo] [WhatsApp] [Llamadas] [En persona]  4 columnas │
├──────────────────────────────────────────────────────────────────────┤
│ Cómo funciona: ①──────②──────③──────④  línea horizontal de 4 pasos   │
│                Registrar Asignar Atender Cerrar   + tabla de plazos   │
├──────────────────────────────────────────────────────────────────────┤
│ Beneficios: 4 columnas                                                │
├──────────────────────────────────────────────────────────────────────┤
│ Cifras en vivo: [128 Total] [14 Pendientes] [23 En proceso] [91 Resueltas] │
├──────────────────────────────────────────────────────────────────────┤
│ Acceso (columna 1/3)        │ Registrar solicitud (columna 2/3)       │
│ Usuario / Contraseña        │ Título            Área destino ▾        │
│ [Iniciar sesión]            │ Descripción (2 col)                     │
│ o "Hola, Juan · TI [Salir]" │ Tipo ▾            Prioridad ▾           │
│                             │ [Registrar]                             │
├──────────────────────────────────────────────────────────────────────┤
│ Mis solicitudes: tabla                                                │
│ Número     Título            Estado       Prioridad  Registro  Límite   │
│ 2026-00041 Instalar impre…   [Registrada] Media      07/10     12/10 [Ver] │
│ [◀ Anterior]  Página 1 de 2  [Siguiente ▶]                            │
│ Panel de detalle debajo de la tabla: datos + historial + cancelar     │
├──────────────────────────────────────────────────────────────────────┤
│ Pie                                                                   │
└──────────────────────────────────────────────────────────────────────┘
```

Puntos de quiebre (móvil primero, `min-width`): 600 px (2 columnas), 900 px (navegación
completa, tabla en Mis solicitudes, Acceso + Registrar lado a lado), 1200 px (contenedor
máximo). Sin desplazamiento horizontal en 360 px; objetivos táctiles de al menos 44×44 px.

## 7. Estados de interfaz

Cada sección que consume la API tiene una región de estado con `role="status"`
(`aria-live="polite"`) para loading/success/empty y `role="alert"` para error. Los mensajes
se insertan con `textContent`; nunca `innerHTML` con datos de la API.

| Sección | loading | empty | success | error | unauthorized |
|---|---|---|---|---|---|
| Cifras en vivo | Tarjetas con "—" y texto "Cargando cifras…"; botón Actualizar deshabilitado | Todas en 0: "Aún no hay solicitudes registradas." | Números + "Actualizado HH:MM" | "No se pudieron cargar las cifras. [Reintentar]" (las tarjetas quedan con "—") | No aplica (endpoint público) |
| Acceso | Botón "Ingresando…" deshabilitado (`aria-busy`) | — | Saludo con nombre y área; se habilitan Registrar y Mis solicitudes | 401 `CREDENCIALES_INVALIDAS`: "Usuario o contraseña incorrectos." · 422: error por campo · 429: "Demasiados intentos. Espere 15 minutos." · 500/red: "Servicio no disponible." | Estado inicial sin sesión: formulario visible |
| Registrar solicitud | Carga de catálogos: selects deshabilitados "Cargando…". Envío: botón "Registrando…" deshabilitado | Catálogo vacío: "No hay áreas/tipos disponibles; contacte al administrador." (envío deshabilitado) | Mensaje con número y fecha límite; formulario limpio; Mis solicitudes se recarga | 422: mensaje bajo cada campo (`aria-describedby`, `aria-invalid`) y foco al primer campo con error · 403 `CSRF_INVALIDO`: se renueva el token con `/auth/me` y se pide reintentar · 500/red: mensaje general; los datos escritos se conservan | Aviso "Inicie sesión para registrar una solicitud" + botón a `#acceso`; formulario oculto |
| Mis solicitudes | "Cargando sus solicitudes…" | "Todavía no ha registrado solicitudes." + enlace a `#registrar` | Lista/tabla, paginación; detalle con historial | 403 `SIN_PERMISO` / 404 `NO_ENCONTRADO` en detalle: mensaje en el panel · 409 `ESTADO_NO_PERMITE` / `CONFLICTO_VERSION` al cancelar: "La solicitud cambió; se recargó el detalle." y se relee · 422: error bajo "Motivo" · 500/red: "No se pudo cargar. [Reintentar]" | "Inicie sesión para ver sus solicitudes." |

**Expiración de sesión:** si cualquier llamada protegida responde 401 `NO_AUTENTICADO`,
`js/api/http.js` lo notifica a `app.js`, que borra el token CSRF en memoria, vuelve Acceso
al estado sin sesión y pone Registrar y Mis solicitudes en *unauthorized* con el mensaje
"Su sesión expiró. Inicie sesión nuevamente."

**Al cargar la página:** `app.js` llama a `GET /auth/me`. 200 ⇒ guarda `csrf_token` en
memoria y activa las secciones con sesión; 401 ⇒ estado sin sesión (no se muestra como
error). Cifras en vivo se carga en paralelo, con o sin sesión.

## 8. Contrato de los endpoints nuevos

Siguen las convenciones de `contrato_solicitudes.md` §1: base `/api/v1`, JSON UTF-8,
sobre `{ ok, data }` / `{ ok, error: { codigo, mensaje, detalles } }`, cabeceras
`Cache-Control: no-store` y `X-Content-Type-Options: nosniff` (ya aplicadas por
`App\Core\Response`). Ambos son de solo lectura (`GET`), por lo que no exigen
`X-CSRF-Token`. No requieren cambios de esquema: `sis_rol_app` ya tiene `SELECT` sobre
`sol_solicitud`, `cat_estado`, `org_area`, `cat_tipo_solicitud` y `cat_prioridad` (`V008`).
Los endpoints existentes no se modifican.

### 8.1 `GET /api/v1/estadisticas` — público

**Propósito:** cifras agregadas de la sección "Cifras en vivo".
**Acceso:** sin sesión y sin middleware (igual que `/health`).
**Controlador:** `App\Controllers\Api\EstadisticaController::show`.
**Lectura:** una sola consulta agregada con `COUNT(*)` agrupada por `estado_id` sobre
`sol_solicitud` (sentencia preparada en un repositorio; sin parámetros del cliente).
Los estados sin solicitudes devuelven 0.

**Parámetros:** ninguno (los parámetros de consulta se ignoran).

**200**
```json
{
  "ok": true,
  "data": {
    "total": 128,
    "registradas": 14,
    "en_proceso": 23,
    "atendidas_cerradas": 91,
    "por_estado": [
      { "codigo": "REGISTRADA", "nombre": "Registrada", "total": 14 },
      { "codigo": "ASIGNADA",   "nombre": "Asignada",   "total": 9 },
      { "codigo": "EN_PROCESO", "nombre": "En proceso", "total": 14 },
      { "codigo": "ATENDIDA",   "nombre": "Atendida",   "total": 6 },
      { "codigo": "CERRADA",    "nombre": "Cerrada",    "total": 85 },
      { "codigo": "RECHAZADA",  "nombre": "Rechazada",  "total": 0 },
      { "codigo": "CANCELADA",  "nombre": "Cancelada",  "total": 0 }
    ],
    "generado_en": "2026-10-08 10:42:00"
  }
}
```

| Campo | Definición |
|---|---|
| `total` | Todas las solicitudes (cualquier estado) |
| `registradas` | Estado REGISTRADA (pendientes de asignar) |
| `en_proceso` | Estados ASIGNADA + EN_PROCESO |
| `atendidas_cerradas` | Estados ATENDIDA + CERRADA |
| `por_estado` | Los 7 estados de `cat_estado` en su `orden`, con su conteo (incluye 0) |
| `generado_en` | Hora del servidor (`YYYY-MM-DD HH:MM:SS`, `APP_TIMEZONE`) |

Enteros JSON (no cadenas). `total` = suma de `por_estado`.

**Privacidad (regla de decisiones_frontend.md):** solo conteos globales. Nunca títulos,
números de solicitud, nombres, áreas ni desgloses por persona o área (un desglose por área
con pocas solicitudes permitiría inferir quién pidió qué).

**Errores:** 500 `ERROR_INTERNO` (BD no disponible; sin detalles). 405
`METODO_NO_PERMITIDO` para otros métodos (lo resuelve el `Router`).

### 8.2 `GET /api/v1/catalogos` — con sesión

**Propósito:** llenar los `select` del formulario Registrar solicitud.
**Acceso:** `AuthMiddleware` (401 `NO_AUTENTICADO` sin sesión). No requiere CSRF (`GET`).
**Controlador:** `App\Controllers\Api\CatalogoController::index`.
**Lectura:** solo registros con `activo = 1` (RN-D12), mediante métodos de lectura nuevos en
`CatalogoRepository` (sentencias preparadas). El backend sigue validando en
`POST /solicitudes`; este endpoint solo ayuda a la interfaz.

**Parámetros:** ninguno.

**200**
```json
{
  "ok": true,
  "data": {
    "areas": [
      { "id": 3, "codigo": "ADM",  "nombre": "Administración" },
      { "id": 4, "codigo": "ALM",  "nombre": "Almacén" },
      { "id": 2, "codigo": "MANT", "nombre": "Mantenimiento" },
      { "id": 1, "codigo": "TI",   "nombre": "Tecnología de la Información" }
    ],
    "tipos_solicitud": [
      { "id": 3, "nombre": "Mantenimiento", "descripcion": "Reparaciones de infraestructura o mobiliario" },
      { "id": 2, "nombre": "Solicitud de materiales", "descripcion": "Pedido de materiales o insumos" },
      { "id": 1, "nombre": "Soporte técnico", "descripcion": "Problemas con equipos, sistemas o red" },
      { "id": 4, "nombre": "Trámite administrativo", "descripcion": "Certificados, permisos y otros trámites" }
    ],
    "prioridades": [
      { "id": 1, "codigo": "BAJA",    "nombre": "Baja",    "horas_max_atencion": 40 },
      { "id": 2, "codigo": "MEDIA",   "nombre": "Media",   "horas_max_atencion": 24 },
      { "id": 3, "codigo": "ALTA",    "nombre": "Alta",    "horas_max_atencion": 8 },
      { "id": 4, "codigo": "URGENTE", "nombre": "Urgente", "horas_max_atencion": 4 }
    ]
  }
}
```
(Ejemplo tomado de la BD local el 2026-10-08; los `id` dependen de la instalación.)

| Lista | Columnas | Orden |
|---|---|---|
| `areas` | `org_area.id, codigo, nombre` | `nombre` |
| `tipos_solicitud` | `cat_tipo_solicitud.id, nombre, descripcion` (`descripcion` puede ser `null`) | `nombre` |
| `prioridades` | `cat_prioridad.id, codigo, nombre, horas_max_atencion` | `orden` |

No se exponen `jefe_usuario_id`, `activo` ni fechas de auditoría. Una lista vacía se
devuelve como `[]` (la interfaz muestra el estado *empty*).

**Errores:** 401 `NO_AUTENTICADO`, 500 `ERROR_INTERNO`, 405 para otros métodos.

### 8.3 Pruebas previstas

| Endpoint | Caso | Esperado | Checkpoint |
|---|---|---|---|
| `/estadisticas` | Sin sesión | 200, enteros, `total` = suma de `por_estado`, sin campos de texto libre | CP-FRONT-03 |
| `/estadisticas` | Tras registrar una solicitud | `total` y `registradas` aumentan en 1 | CP-FRONT-04 |
| `/estadisticas` | `POST` | 405 | CP-FRONT-03 |
| `/catalogos` | Sin sesión | 401 `NO_AUTENTICADO` | CP-FRONT-04 |
| `/catalogos` | Con sesión | 200, solo activos, sin `jefe_usuario_id` | CP-FRONT-04 |

Se agregarán a `tests/api_test.php` y a `docs/openapi.yaml` en CP-FRONT-06.

### 8.4 Verificación de factibilidad (CP-FRONT-01)

El 2026-10-08 se ejecutaron contra `sis_solicitudes`, con la cuenta de la aplicación, solo
consultas `SELECT` equivalentes a las previstas (sin crear código de la aplicación):
conteo por estado con `cat_estado LEFT JOIN sol_solicitud` (devolvió los 7 estados, incluidos
los de 0: REGISTRADA 2, CANCELADA 6, resto 0) y las tres listas de catálogos activos
(4 áreas, 4 tipos, 4 prioridades). Los permisos de `sis_rol_app` bastan; no hay cambios de
esquema.

## 9. Mapa de archivos y responsabilidades (para CP-FRONT-02..05)

Según `.agents/guides/estructura_frontend.md`:

| Pieza | Archivo | Checkpoint |
|---|---|---|
| Ruta `/` | `routes/web.php` → `Web\LandingController::index` | 02 |
| Layout y parciales | `Views/layouts/main.php`, `Views/partials/header.php`, `footer.php` | 02 |
| Vista y secciones | `Views/landing/index.php`, `Views/landing/secciones/{inicio,problema,como-funciona,beneficios,cifras,acceso,registrar,mis-solicitudes}.php` | 02 (estáticas), 03–05 (contenedores dinámicos) |
| Estilos | `public/assets/css/{variables,base,layout,components,forms,responsive}.css` | 02 |
| HTTP común | `js/api/http.js` (fetch, `credentials: 'same-origin'`, `X-CSRF-Token` en memoria, normaliza `{ok,data,error}`, avisa 401) | 03 |
| Clientes API | `js/api/{auth,estadisticas,catalogos,solicitudes}-api.js` | 03–05 |
| Módulos | `js/modules/{estadisticas,acceso,registro-solicitud,mis-solicitudes}.js` | 03–05 |
| Arranque | `js/app.js` (`type="module"`, llama a `/auth/me`, inicia módulos, coordina sesión) | 03 |
| Endpoints | `Controllers/Api/EstadisticaController.php`, `CatalogoController.php`; lecturas en repositorios | 03, 04 |

**Integración con el front controller actual:** `public/index.php` atiende hoy solo la API.
En CP-FRONT-02 se registrará `routes/web.php` en el mismo `Router` para `GET /`; las rutas
`/api/v1/*` siguen igual. Los archivos de `public/assets/` los sirve directamente el
servidor (`php -S` entrega archivos existentes antes de llamar a `index.php`).
La vista HTML se entrega con `Content-Type: text/html; charset=utf-8`, sin datos de la BD
(los datos llegan solo por `fetch`), y todo valor impreso desde PHP pasa por
`htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`.

## 10. Accesibilidad (criterios de diseño; verificación en CP-FRONT-06)

- HTML semántico: `header`, `nav`, `main`, `section` con `aria-labelledby`, `footer`;
  un `h1`, jerarquía sin saltos.
- Toda entrada con `<label for>`; ayudas y errores enlazados con `aria-describedby`;
  campos obligatorios con `required` y texto "(obligatorio)".
- Foco visible (`:focus-visible`, 3 px `--color-foco` + contorno); orden de tabulación =
  orden visual; tras registrar, el foco va al mensaje de éxito; al abrir el detalle, a su
  título; el botón "Menú" usa `aria-expanded` y `aria-controls`.
- Contraste AA verificado (§4); el estado nunca se comunica solo con color.
- Tabla de Mis solicitudes con `<caption>` y `<th scope="col">`.
- Funciona sin ratón; sin trampas de foco (no se usan ventanas modales: el detalle y la
  confirmación de cancelación son paneles en línea).
- Sin JavaScript, las secciones informativas (1–4 y pie) se leen completas; las dinámicas
  muestran "Esta sección requiere JavaScript" mediante `<noscript>`.

## 11. Seguridad en el frontend

- Token CSRF solo en una variable de módulo JS; se obtiene de `login` o `me`; nunca en
  `localStorage`, `sessionStorage` ni en el HTML.
- La contraseña no se guarda; el campo se vacía tras cada intento.
- Datos de la API insertados con `textContent` / `createElement`.
- `fetch` solo a rutas relativas del mismo origen (`/api/v1/...`), `credentials: 'same-origin'`.
- Sin credenciales, claves ni URLs de BD en JS o vistas; sin CDNs.
- La validación JS (longitudes, obligatorios) solo ayuda; el backend decide (422).

## 12. Trazabilidad

| Elemento | Origen |
|---|---|
| Secciones 1–4, 9 | decisiones_frontend.md "Secciones de la página" 1–3, 8; requisito "Comunica su propósito" |
| Cifras en vivo + `/estadisticas` | decisiones_frontend.md sección 4 y "Cambios en el backend"; requisito "Fuente de datos" |
| Acceso | RF-01; decisiones_frontend.md sección 5 (sin registro público) |
| Registrar + `/catalogos` | RF-02, RF-03, RN-D12 (catálogos activos), RN-D07 (plazos por prioridad) |
| Mis solicitudes, detalle, cancelar | RF-09, RF-14, RN-D11/RC-05 (motivo), DF-06 (`version`) |
| Estados de interfaz | estructura_frontend.md "Estados obligatorios" |
| Responsive (360/768/1280) | decisiones_frontend.md "Adaptable a dispositivos"; CP-FRONT-06 |
| CSRF en memoria, `textContent` | decisiones_frontend.md "Tecnología"; estructura_frontend.md "Seguridad" |

## 13. Fuera de alcance

Registro de cuentas, recuperación de contraseña, edición de solicitudes desde la landing,
panel del jefe, asignación, cambios de estado distintos de cancelar, comentarios, adjuntos,
notificaciones y reportes.
