# Contrato de la API — Solicitudes internas (CP-API-01)

**Sistema:** Sistema de seguimiento de solicitudes internas
**Checkpoint:** CP-API-01 — Contrato (sin código)
**Fecha:** 2026-10-07
**Base de datos:** `sis_solicitudes` (MySQL 8 / InnoDB), cuenta `sis_app` con TLS
**Skills aplicados:** `api-design-principles`, `error-handling-patterns`, `auth-implementation-patterns`, `php-development`
**Fuentes:** `04_decisiones/decisiones_api.md`, `04_decisiones/decisiones_arquitectura.md`,
`database/V003` (tablas, CHECK), `V006` (triggers TRG-01..TRG-04), `V008` (permisos de `sis_rol_app`),
`V009` (catálogos e IDs fijos), `11_transacciones_concurrencia/transacciones_concurrencia.md` (T-01, T-03, T-04, T-12),
`08_auditoria/auditoria.md`, `01_requisitos_datos/requisitos_datos.md` (RN-D01..RN-D13).

Alcance: RF-01 (login, como requisito previo), RF-02 (registrar), RF-03 (número y fecha automáticos),
RF-09 (consultar mis solicitudes e historial) y RF-14 (cancelar), más la edición en REGISTRADA (RN-D10).
Fuera de alcance: asignación, cambio de prioridad por el jefe, cambios de estado distintos de cancelar,
comentarios, adjuntos, notificaciones, reportes y administración.

---

## 1. Convenciones generales

| Tema | Convención |
|---|---|
| Base | `/api/v1` |
| Formato | JSON UTF-8. Peticiones con cuerpo: `Content-Type: application/json`. Respuestas: `Content-Type: application/json; charset=utf-8` |
| Nombres | Recursos en plural y en español (`/solicitudes`); campos en `snake_case`, iguales a las columnas cuando existen |
| Fechas | Cadena `YYYY-MM-DD HH:MM:SS` en la zona horaria de la aplicación (`APP_TIMEZONE`); la sesión MySQL usa la misma zona. Filtros de fecha: `YYYY-MM-DD` |
| Identificadores | `id` numérico (BIGINT) en la ruta; `numero` (`AAAA-NNNNN`) solo de lectura |
| Estados | Se exponen como objeto `{ "id": 1, "codigo": "REGISTRADA", "nombre": "Registrada" }` (IDs fijos de `V009`) |
| Autenticación | Sesión PHP (cookie `httpOnly`, `SameSite=Lax`, `Secure` fuera de `local`) |
| CSRF | Toda petición `POST`, `PUT` o `DELETE` con sesión iniciada exige la cabecera `X-CSRF-Token` con el token entregado por `login` o `me` (excepto `POST /auth/login`) |
| Concurrencia | `PUT` y `DELETE` sobre una solicitud exigen el campo `version` leído en el detalle (control optimista, DF-06) |
| Auditoría de conexión | Antes de cualquier escritura, la conexión fija `@sis_usuario_id` (id de la sesión o `NULL`) y `@sis_ip` (IP del cliente) |
| Aislamiento | `SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED`, `innodb_lock_wait_timeout = 10` |

### 1.1 Respuesta exitosa

```json
{ "ok": true, "data": { ... } }
```

Listados:

```json
{ "ok": true, "data": [ ... ], "meta": { "pagina": 1, "por_pagina": 20, "total": 37, "total_paginas": 2 } }
```

### 1.2 Respuesta de error

```json
{
  "ok": false,
  "error": {
    "codigo": "VALIDACION",
    "mensaje": "Los datos enviados no son válidos.",
    "detalles": { "titulo": ["El título es obligatorio."] }
  }
}
```

`detalles` es un objeto `campo → lista de mensajes` en errores de validación y `null` en el resto.
Nunca se devuelven trazas, SQL, nombres de tablas ni mensajes crudos de PDO/MySQL (`APP_DEBUG` no cambia esto en la respuesta; los detalles van solo al log del servidor).

### 1.3 Códigos HTTP y códigos de error

| HTTP | `error.codigo` | Cuándo |
|---|---|---|
| 200 | — | Lectura, edición, cancelación, logout correctos |
| 201 | — | Solicitud registrada (cabecera `Location: /api/v1/solicitudes/{id}`) |
| 400 | `JSON_INVALIDO` | Cuerpo no es JSON válido, no es un objeto o `Content-Type` incorrecto |
| 400 | `PARAMETRO_INVALIDO` | `{id}` no es un entero positivo; parámetros de consulta mal formados |
| 401 | `NO_AUTENTICADO` | Sin sesión o sesión expirada |
| 401 | `CREDENCIALES_INVALIDAS` | Usuario inexistente, inactivo o contraseña incorrecta (mensaje único) |
| 403 | `CSRF_INVALIDO` | Falta `X-CSRF-Token` o no coincide |
| 403 | `SIN_PERMISO` | La solicitud existe pero no pertenece al usuario (RNF-01) |
| 404 | `NO_ENCONTRADO` | La solicitud no existe |
| 404 | `RUTA_NO_ENCONTRADA` | Ruta desconocida |
| 405 | `METODO_NO_PERMITIDO` | Ruta conocida con método no soportado (cabecera `Allow`) |
| 409 | `ESTADO_NO_PERMITE` | El estado actual no admite la acción (RN-D10, RN-D04, RN-D11, RC-03) |
| 409 | `CONFLICTO_VERSION` | `version` enviada distinta de la actual (otro usuario la modificó) |
| 422 | `VALIDACION` | Reglas de campo o catálogo inexistente/inactivo (RN-D12) |
| 429 | `DEMASIADOS_INTENTOS` | Límite de intentos de login por IP/usuario superado (ver §3.1) |
| 500 | `ERROR_INTERNO` | Cualquier error no previsto o BD no disponible |
| 503 | `SERVICIO_NO_DISPONIBLE` | Solo en `/health` cuando la BD no responde |

### 1.4 Traducción de errores del motor (transacciones_concurrencia.md §8)

La validación de la aplicación se ejecuta primero; los triggers y CHECK son la última barrera.
Si aun así el motor rechaza la operación, el repositorio/servicio traduce:

| Error MySQL | Origen | Respuesta |
|---|---|---|
| 1644 / SQLSTATE 45000 con prefijo `RN-D10`, `RN-D11`, `RN-D04`, `RC-03` | TRG-02 / TRG-03 | 409 `ESTADO_NO_PERMITE` |
| 1644 / 45000 con prefijo `RN-D12`, `RC-05` | TRG-01 / TRG-02 / TRG-03 | 422 `VALIDACION` |
| 1644 / 45000 con prefijo `INT-01`, `RN-D13`, `RN-D01` | TRG-01 / TRG-02 / TRG-04 | 500 `ERROR_INTERNO` (indica un fallo de programación; se registra) |
| 3819 (CHECK) | V003 | 422 `VALIDACION` (nombre de la restricción solo en el log) |
| 1062 en `uq_sol_solicitud_numero` | carrera de número | reintento interno (hasta 3) y luego 500 |
| 1213 / 1205 | interbloqueo / espera agotada | reintento interno (hasta 3, espera aleatoria 50–200 ms) y luego 500 |
| 1452 | FK inexistente | 422 `VALIDACION` |
| Otro | — | 500 `ERROR_INTERNO` |

---

## 2. Salud

### `GET /api/v1/health`

Sin sesión. Ejecuta `SELECT 1`.

**200**
```json
{ "ok": true, "data": { "estado": "ok", "base_datos": "ok", "hora_servidor": "2026-10-07 09:15:00" } }
```

**503** `SERVICIO_NO_DISPONIBLE` si la conexión o el `SELECT 1` fallan (sin detalles del error).

---

## 3. Autenticación (RF-01, RNF-01)

### 3.1 `POST /api/v1/auth/login`

**Entrada**
```json
{ "nombre_usuario": "jperez", "password": "********" }
```

| Campo | Reglas |
|---|---|
| `nombre_usuario` | obligatorio, cadena, `trim`, 3..50 caracteres (`ck_seg_usuario_nombre_usuario`, VARCHAR(50)) |
| `password` | obligatorio, cadena, 1..255 caracteres; no se aplica `trim`; nunca se registra |

**Proceso (T-12)**
1. Busca por `nombre_usuario` (PA-01, `uq_seg_usuario_nombre_usuario`).
2. Si no existe o `activo = 0` o `password_verify()` falla ⇒ 401 `CREDENCIALES_INVALIDAS` (mismo mensaje y tiempo aproximado: si el usuario no existe se verifica contra un hash ficticio).
   Se inserta `aud_auditoria` con `accion = 'LOGIN_FALLIDO'`, `entidad = 'seg_usuario'`, `registro_id = id` o `'-'`, `valores_nuevos = {"nombre_usuario_intentado": "..."}` (sin contraseña).
3. Si es correcto: `session_regenerate_id(true)`, guarda en sesión `usuario_id`, roles y un `csrf_token` aleatorio (32 bytes, hex);
   `UPDATE seg_usuario SET ultimo_acceso_en = NOW()`; `INSERT aud_auditoria` `LOGIN`.
   Si `password_needs_rehash()` es verdadero, se actualiza `password_hash`.
4. Límite de intentos (seguridad.md: la limitación es de la capa de aplicación): 5 fallos consecutivos para el mismo `nombre_usuario` + IP en 15 minutos ⇒ 429 durante 15 minutos (contador en la sesión/almacenamiento local de PHP; no se modifica el esquema).

**200**
```json
{
  "ok": true,
  "data": {
    "usuario": {
      "id": 12, "nombre_usuario": "jperez", "nombres": "Juan", "apellidos": "Pérez",
      "correo": "jperez@institucion.local",
      "area": { "id": 3, "codigo": "TI", "nombre": "Tecnología" },
      "roles": ["SOLICITANTE"]
    },
    "csrf_token": "3f9c...e1"
  }
}
```
Nunca se devuelve `password_hash`.

**Errores:** 400 `JSON_INVALIDO`, 422 `VALIDACION`, 401 `CREDENCIALES_INVALIDAS`, 429 `DEMASIADOS_INTENTOS`, 500.

### 3.2 `POST /api/v1/auth/logout`

Requiere sesión y `X-CSRF-Token`. Vacía `$_SESSION`, destruye la sesión y expira la cookie.

**200** `{ "ok": true, "data": { "mensaje": "Sesión cerrada." } }`
**Errores:** 401 `NO_AUTENTICADO`, 403 `CSRF_INVALIDO`.

### 3.3 `GET /api/v1/auth/me`

Requiere sesión. Relee el usuario en la BD (si fue inactivado, destruye la sesión y responde 401).

**200** Igual que el `data` de login (`usuario` + `csrf_token` vigente).
**Errores:** 401 `NO_AUTENTICADO`.

### 3.4 Autorización

- Todos los endpoints de `/solicitudes` requieren sesión (401 sin ella).
- Cualquier funcionario autenticado y activo puede registrar (RF-02: "cualquier funcionario").
- Ver, editar y cancelar: solo el **solicitante** de la solicitud (`solicitante_id = usuario de la sesión`). En esta fase ningún rol (tampoco JEFE_AREA) amplía la visibilidad; si no es el dueño ⇒ 403 `SIN_PERMISO`.
- La sesión expira tras 30 minutos de inactividad (`config/session.php`).

---

## 4. Solicitudes

### 4.1 Representación

**Resumen (listado)**
```json
{
  "id": 41,
  "numero": "2026-00041",
  "titulo": "Instalar impresora en Contabilidad",
  "area_destino": { "id": 3, "nombre": "Tecnología" },
  "tipo_solicitud": { "id": 2, "nombre": "Soporte técnico" },
  "prioridad": { "id": 2, "codigo": "MEDIA", "nombre": "Media" },
  "estado": { "id": 1, "codigo": "REGISTRADA", "nombre": "Registrada" },
  "fecha_registro": "2026-10-07 09:20:11",
  "fecha_limite": "2026-10-12 09:20:11",
  "vencida": false
}
```
`vencida` = `estado` no final y `fecha_limite < NOW()` (cálculo de lectura, no se guarda).

**Detalle** = resumen + :
```json
{
  "descripcion": "La impresora HP del 2.º piso no está instalada...",
  "solicitante": { "id": 12, "nombres": "Juan", "apellidos": "Pérez" },
  "responsable": null,
  "fecha_atencion": null,
  "fecha_cierre": null,
  "version": 0,
  "acciones": { "puede_editar": true, "puede_cancelar": true },
  "historial": [
    {
      "id": 101,
      "tipo_evento": "REGISTRO",
      "fecha": "2026-10-07 09:20:11",
      "usuario": { "id": 12, "nombres": "Juan", "apellidos": "Pérez" },
      "estado_anterior": null,
      "estado_nuevo": { "id": 1, "codigo": "REGISTRADA", "nombre": "Registrada" },
      "motivo": null,
      "detalle": { "prioridad_id": 2, "fecha_limite": "2026-10-12 09:20:11" }
    }
  ]
}
```
`historial` ordenado por `id` ascendente (PA-06, `ix_sol_historial_solicitud`). No se expone `ip_origen`.
`responsable` es `{id, nombres, apellidos}` o `null`.

### 4.2 Reglas de validación de campos

| Campo | Tipo | Reglas | Fuente |
|---|---|---|---|
| `titulo` | string | obligatorio; `trim`; 1..150 caracteres | VARCHAR(150), `ck_sol_solicitud_titulo` |
| `descripcion` | string | obligatorio; `trim`; 1..5000 caracteres (límite de aplicación, TEXT admite 65 535 bytes) | TEXT NOT NULL |
| `area_destino_id` | integer | obligatorio; > 0; debe existir en `org_area` con `activo = 1` | TRG-01 (RN-D12), FK |
| `tipo_solicitud_id` | integer | obligatorio; > 0; debe existir en `cat_tipo_solicitud` con `activo = 1` | TRG-01/TRG-02 (RN-D12) |
| `prioridad_id` | integer | obligatorio; 1..4; debe existir en `cat_prioridad` con `activo = 1` | TRG-01 (RN-D12), RN-D07 |
| `version` | integer | obligatorio en PUT/DELETE; ≥ 0 | DF-06 |
| `motivo` | string | obligatorio en DELETE; `trim`; 1..500 caracteres | `sol_historial.motivo` VARCHAR(500), TRG-03 (RC-05) |

- Los enteros se aceptan como número JSON o cadena numérica sin signo; cualquier otro valor ⇒ 422.
- Campos no reconocidos se ignoran. Los campos controlados por el sistema (`numero`, `estado_id`, `solicitante_id`, `fecha_registro`, `fecha_limite`, `responsable_id`, `version` en POST) se ignoran si se envían: nunca se toman del cliente.
- Se devuelven todos los errores de campo juntos en `detalles`.

### 4.3 `POST /api/v1/solicitudes` — Registrar (RF-02, RF-03)

Requiere sesión y `X-CSRF-Token`.

**Entrada**
```json
{
  "titulo": "Instalar impresora en Contabilidad",
  "descripcion": "La impresora HP del 2.º piso no está instalada en los equipos del área.",
  "area_destino_id": 3,
  "tipo_solicitud_id": 2,
  "prioridad_id": 2
}
```

**Proceso (transacción T-01, orden global de bloqueo §4)**
1. Validar campos (§4.2) y catálogos activos (lecturas sin bloqueo).
2. `BEGIN`; fijar `@sis_usuario_id`, `@sis_ip`.
3. `SELECT NOW()` ⇒ `ts` (fecha de registro y año del número; cumple `ck_sol_solicitud_numero_anio`).
4. Número: `INSERT INTO sol_secuencia (anio, ultimo_valor) VALUES (YEAR(ts), LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE ultimo_valor = LAST_INSERT_ID(ultimo_valor + 1)`; `SELECT LAST_INSERT_ID()` ⇒ `n`; `numero = AAAA-` + `n` con 5 dígitos. Si `n > 99999` el CHECK `ck_sol_secuencia_tope` falla ⇒ 500.
5. `fecha_limite` = `ts` + `cat_prioridad.horas_max_atencion` **horas laborales** (§4.8).
6. `INSERT sol_solicitud` con `estado_id = 1`, `responsable_id = NULL`, `fecha_registro = ts`, `version = 0` (TRG-01 revalida estado inicial y catálogos; CHECK `fecha_limite > fecha_registro`).
7. `INSERT sol_historial`: `tipo_evento = 'REGISTRO'`, `usuario_id` = sesión, `estado_anterior_id = NULL`, `estado_nuevo_id = 1`, `transicion_id = NULL` (`ck_sol_historial_transicion`), `prioridad_nueva_id = prioridad_id`, `detalle = {"prioridad_id": ..., "fecha_limite": "..."}`, `ip_origen`.
8. `COMMIT`. (El paso 8 de T-01 — notificación al jefe — queda **fuera de alcance** en esta fase.)

**201** + `Location`: `data` = detalle completo (§4.1) con `historial` de un elemento.
**Errores:** 400, 401, 403 `CSRF_INVALIDO`, 422 `VALIDACION`, 500.

### 4.4 `GET /api/v1/solicitudes` — Mis solicitudes (RF-09)

Requiere sesión. Siempre filtra `solicitante_id = usuario de la sesión` (PA-05, `ix_sol_solicitud_solicitante`).

**Parámetros de consulta (todos opcionales)**

| Parámetro | Reglas |
|---|---|
| `estado` | código de `cat_estado` (`REGISTRADA`, `ASIGNADA`, `EN_PROCESO`, `ATENDIDA`, `CERRADA`, `RECHAZADA`, `CANCELADA`) |
| `prioridad_id` | entero 1..4 |
| `desde`, `hasta` | `YYYY-MM-DD`; filtran `fecha_registro` (`hasta` inclusivo: `< hasta + 1 día`); `desde ≤ hasta` |
| `q` | texto 1..100; busca en `titulo` o `numero` (`LIKE` con parámetros y comodines escapados) |
| `pagina` | entero ≥ 1 (defecto 1) |
| `por_pagina` | entero 1..50 (defecto 20) |

Orden fijo: `fecha_registro DESC, id DESC`. Valor no válido ⇒ 400 `PARAMETRO_INVALIDO` con `detalles`.

**200** `data` = lista de resúmenes; `meta` con paginación. Lista vacía ⇒ `data: []`, `total: 0`.
**Errores:** 400, 401, 500.

### 4.5 `GET /api/v1/solicitudes/{id}` — Detalle con historial (RF-09)

Requiere sesión. `{id}` entero positivo (si no ⇒ 400 `PARAMETRO_INVALIDO`).

1. Lee la solicitud por PK. No existe ⇒ 404 `NO_ENCONTRADO`.
2. `solicitante_id` ≠ sesión ⇒ 403 `SIN_PERMISO`.
3. Lee el historial (PA-06).

`acciones.puede_editar` = estado REGISTRADA; `acciones.puede_cancelar` = estado REGISTRADA o ASIGNADA.

**200** detalle (§4.1). **Errores:** 400, 401, 403, 404, 500.

### 4.6 `PUT /api/v1/solicitudes/{id}` — Editar (RN-D10)

Requiere sesión y `X-CSRF-Token`. Reemplaza los campos editables.

**Entrada**
```json
{
  "titulo": "Instalar impresora en Contabilidad (2.º piso)",
  "descripcion": "Texto corregido...",
  "tipo_solicitud_id": 2,
  "version": 0
}
```

Campos editables: `titulo`, `descripcion`, `tipo_solicitud_id` (los tres obligatorios).
**No editables:** `area_destino_id` (inmutable por TRG-02 `INT-01`) y `prioridad_id` (tras el registro solo la cambia el jefe, RN-D07/RN-D08, T-05, fuera de alcance). Si se envían con un valor distinto del actual ⇒ 422 `VALIDACION` con mensaje explicativo; si coinciden, se ignoran.

**Proceso (T-04)**
1. Validar `{id}` y cuerpo (§4.2).
2. `BEGIN`; fijar variables de sesión; `SELECT ... FROM sol_solicitud WHERE id = ? FOR UPDATE`.
3. No existe ⇒ 404; no es el solicitante ⇒ 403; `estado_id ≠ 1` ⇒ 409 `ESTADO_NO_PERMITE`; `version` distinta ⇒ 409 `CONFLICTO_VERSION`.
4. Si ningún campo cambia (comparación binaria, como TRG-02) ⇒ `ROLLBACK` y 200 con el detalle actual (sin historial nuevo ni cambio de `version`).
5. `UPDATE sol_solicitud SET titulo = ?, descripcion = ?, tipo_solicitud_id = ?, version = version + 1 WHERE id = ? AND version = ?` (0 filas ⇒ 409 `CONFLICTO_VERSION`).
6. `INSERT sol_historial`: `tipo_evento = 'EDICION'`, `usuario_id`, `transicion_id = NULL`, `detalle` = solo campos modificados `{"titulo": {"antes": "...", "despues": "..."}, ...}` (obligatorio por `ck_sol_historial_edicion`), `ip_origen`.
7. `COMMIT`.

**200** detalle actualizado (nueva `version`).
**Errores:** 400, 401, 403, 404, 409 (`ESTADO_NO_PERMITE`, `CONFLICTO_VERSION`), 422, 500.

### 4.7 `DELETE /api/v1/solicitudes/{id}` — Cancelar (RF-14, RN-D04)

**No borra la fila.** Cambia el estado a CANCELADA (7). Requiere sesión y `X-CSRF-Token`.

**Entrada** (cuerpo JSON en el DELETE)
```json
{ "motivo": "Ya no se necesita: se resolvió con el proveedor.", "version": 0 }
```

**Proceso (T-03 aplicado a T03/T06)**
1. Validar `{id}`, `motivo` (1..500 tras `trim`) y `version`. Sin motivo ⇒ 422.
2. `BEGIN`; fijar variables; `SELECT ... FOR UPDATE`.
3. No existe ⇒ 404; no es el solicitante ⇒ 403; `version` distinta ⇒ 409 `CONFLICTO_VERSION`.
4. Buscar la transición en `cat_transicion_estado` con `estado_origen_id = estado actual`, `estado_destino_id = 7`, `rol_id = 1` (SOLICITANTE), `activa = 1`:
   REGISTRADA ⇒ T03 (id 3); ASIGNADA ⇒ T06 (id 6). Cualquier otro estado (EN_PROCESO, ATENDIDA o final) ⇒ 409 `ESTADO_NO_PERMITE`.
5. `UPDATE sol_solicitud SET estado_id = 7, fecha_cierre = NOW(), version = version + 1 WHERE id = ? AND version = ?`
   (`ck_sol_solicitud_cierre_estado` exige `fecha_cierre` en estados 5/6/7; `responsable_id` se conserva si estaba ASIGNADA; TRG-02 revalida transición y estado final).
6. `INSERT sol_historial`: `tipo_evento = 'CAMBIO_ESTADO'`, `transicion_id` (3 o 6), `estado_anterior_id`, `estado_nuevo_id = 7`, `motivo`, `usuario_id`, `ip_origen` (TRG-03 revalida transición activa y motivo).
7. `COMMIT`. No hay `DELETE FROM` en ningún punto.

**200** detalle actualizado (estado CANCELADA, `fecha_cierre`, historial con el evento).
**Errores:** 400, 401, 403, 404, 409 (`ESTADO_NO_PERMITE`, `CONFLICTO_VERSION`), 422, 500.

### 4.8 Cálculo de la fecha límite (RN-D08, D-05)

Entrada: `ts` (fecha de registro), `H` = `horas_max_atencion` de la prioridad, `cfg_horario_laboral` (días activos y franja; V009: lunes a viernes 08:30–16:30) y `cfg_feriado`.

1. Si `ts` cae fuera de la franja laboral (antes del inicio, después del fin, día inactivo o feriado), el conteo empieza en el inicio de la siguiente franja laboral; si cae dentro, empieza en `ts`.
2. Se consumen minutos laborales día a día hasta sumar `H × 60`; la fecha límite es el instante en que se completan.
3. Se lee el horario una vez y los feriados del rango `[fecha(ts), fecha(ts) + 60 días]`; si no hubiera ningún día laboral activo ⇒ 500 (configuración inválida).
4. El resultado siempre es posterior a `ts` (`H > 0` por `ck_cat_prioridad_horas`), lo que cumple `ck_sol_solicitud_limite`.

Ejemplos de referencia con V009 y sin feriados (pruebas de CP-API-03):

| Registro | Prioridad | Cálculo | Fecha límite |
|---|---|---|---|
| mié 2026-10-07 09:20:11 | MEDIA (24 h) | mié 7 h 10 min + jue 8 h + vie 8 h = 23 h 10 min; faltan 50 min ⇒ lun 08:30 + 0:50 | lun 2026-10-12 09:20:11 |
| mié 2026-10-07 09:20:11 | URGENTE (4 h) | dentro de la franja del mismo día | mié 2026-10-07 13:20:11 |
| vie 2026-10-09 18:00:00 | ALTA (8 h) | fuera de franja ⇒ inicia lun 08:30; 8 h completas | lun 2026-10-12 16:30:00 |

---

## 5. Matriz de pruebas que el contrato exige (CP-API-02 a CP-API-04)

| Caso | Esperado |
|---|---|
| `GET /health` con BD arriba / caída | 200 / 503 |
| Login correcto / contraseña errónea / usuario inactivo / cuerpo vacío | 200 + cookie / 401 / 401 / 422 |
| `GET /auth/me` sin sesión | 401 |
| POST/PUT/DELETE sin `X-CSRF-Token` | 403 `CSRF_INVALIDO` |
| Registrar válido | 201, `numero` `AAAA-NNNNN`, estado REGISTRADA, historial REGISTRO |
| Registrar con título vacío, prioridad 9, área inactiva | 422 con `detalles` |
| Registrar sin sesión | 401 |
| Listar con filtros y paginación | 200 solo con solicitudes propias |
| Detalle de solicitud ajena (jefe de área demo) | 403 |
| Detalle inexistente / `id = abc` | 404 / 400 |
| Editar REGISTRADA con `version` correcta | 200, `version + 1`, historial EDICION |
| Editar con `version` vieja | 409 `CONFLICTO_VERSION` |
| Editar solicitud CANCELADA | 409 `ESTADO_NO_PERMITE` |
| Cambiar `area_destino_id` en PUT | 422 |
| Cancelar REGISTRADA con motivo | 200, estado CANCELADA, `fecha_cierre`, historial CAMBIO_ESTADO T03 |
| Cancelar sin motivo | 422 |
| Cancelar solicitud ya CANCELADA | 409 `ESTADO_NO_PERMITE` |

---

## 6. Trazabilidad

| Elemento del contrato | RF / RNF / regla | Garantía en la BD |
|---|---|---|
| Login, sesión, `password_verify`, auditoría LOGIN | RF-01, RNF-01, RNF-05 | `uq_seg_usuario_nombre_usuario`, `aud_auditoria` (INSERT permitido a `sis_rol_app`) |
| Registrar | RF-02, RN-D12 | TRG-01, FK, CHECK de título |
| Número y fecha automáticos | RF-03, RN-D01 | `sol_secuencia`, `uq_sol_solicitud_numero`, `ck_sol_solicitud_numero_*` |
| Fecha límite por prioridad | RN-D08, RF-11 (dato) | `ck_sol_solicitud_limite` |
| Listar / detalle con historial | RF-09, RNF-02 | `ix_sol_solicitud_solicitante`, `ix_sol_historial_solicitud` |
| Solo el dueño accede | RNF-01 | Servicio PHP (integridad.md: reglas de actor) |
| Editar solo en REGISTRADA | RN-D10 | TRG-02 (d) |
| Área destino inmutable | S-04, RF-03 | TRG-02 (a) `INT-01` |
| Cancelar con motivo, solo REGISTRADA/ASIGNADA | RF-14, RN-D04 | T03/T06, TRG-02 (c), TRG-03 (RC-05), `ck_sol_solicitud_cierre_estado` |
| Estados finales inmutables | RN-D11 | TRG-02 (b) |
| Historial de cada cambio | RNF-05, RN-D13 | `sol_historial` solo INSERT (TRG-04; `sis_rol_app` sin UPDATE/DELETE) |
| Concurrencia | DF-06 | `version`, `FOR UPDATE` |
| Sin borrado físico | R-06 | `sis_rol_app` sin DELETE sobre `sol_solicitud` |
