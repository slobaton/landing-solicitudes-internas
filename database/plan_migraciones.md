# Paso 13 — Plan de migraciones

**Sistema:** Sistema de seguimiento de solicitudes internas
**Fecha:** 2026-10-06
**Agente:** database-engineer — Claude Opus 5.5 (claude-opus-5-5)
**Skills aplicadas:** `database-schema-designer` (migraciones reversibles, compatibles hacia atrás) y `databases` (comprobación de seguridad de migraciones, autocomprobación)
**Motor:** MySQL 8.4 LTS / InnoDB
**Entradas:** `versionamiento.md` (convención y mapa V001–V010), `seguridad.md` (cuentas), `integridad.md`, `indices_rendimiento.md`

> Estrategia de avance y reversión **preparada antes de ejecutar**. Este paso no ejecuta nada. La ejecución corresponde al paso 17 y solo con `STATUS: APPROVED` del paso 14.

---

## 1. Alcance

Migración inicial (base vacía → esquema v1). No hay datos previos que migrar: el sistema es nuevo. Los riesgos son de **orden**, **privilegios** y **ejecución parcial** (MySQL no tiene DDL transaccional).

## 2. Orden de ejecución y cuenta

| Orden | Script | Cuenta | Precondición | Verificación posterior |
|---|---|---|---|---|
| 1 | `V001__crear_base_datos.sql` | DBA | No existe `cfg_version_esquema` | Base con `utf8mb4` / `utf8mb4_0900_ai_ci`; fila V001 |
| 2 | `V002__crear_cuenta_migrador.sql` | DBA | Marcadores sustituidos | `SHOW GRANTS FOR sis_migrador`; fila V002 |
| 3 | `V003__crear_tablas.sql` | `sis_migrador` | V002 aplicada | 21 tablas InnoDB; fila V003 |
| 4 | `V004__crear_indices.sql` | `sis_migrador` | V003 | Índices del paso 11; fila V004 |
| 5 | `V005__crear_claves_foraneas.sql` | `sis_migrador` | V004 (índices previos a las FK) | FK en `information_schema.REFERENTIAL_CONSTRAINTS`, todas `RESTRICT`; fila V005 |
| 6 | `V006__crear_triggers_integridad.sql` | `sis_migrador` | V005 | Triggers TRG-01 a TRG-10; fila V006 |
| 7 | `V007__crear_triggers_auditoria.sql` | `sis_migrador` | V006 | Triggers de auditoría; fila V007 |
| 8 | `V008__crear_roles_y_cuentas.sql` | DBA | V007; marcadores sustituidos | Roles y cuentas; `SHOW GRANTS ... USING ...`; fila V008 |
| 9 | `V009__datos_iniciales_catalogos.sql` | `sis_migrador` | V008 | Conteos de catálogos; auditoría de la carga con `usuario_id` nulo; fila V009 |
| 10 | `V010__datos_iniciales_administrador.sql` (opcional) | `sis_migrador` | V009; marcadores sustituidos | Un área, un administrador con roles SOLICITANTE + ADMINISTRADOR |
| — | `99_verificacion.sql` | Cualquiera con lectura | Tras cada script o al final | Ver §6 |

**Por qué la creación de triggers con `sis_migrador`:** así el `DEFINER` de los triggers es la cuenta de migración (estable, no personal). Si se ejecutaran con una cuenta personal y esta se eliminara, todos los triggers fallarían.

**Advertencia de privilegios (binlog activo):** con `log_bin = ON` y `log_bin_trust_function_creators = 0`, MySQL puede exigir privilegios elevados para crear triggers. Opciones para el paso 17, a decidir por el DBA: (a) activar temporalmente `log_bin_trust_function_creators = 1` durante V006–V007; o (b) ejecutar V006–V007 con la cuenta DBA indicando `DEFINER = sis_migrador` (requiere `SET_ANY_DEFINER` en 8.2+ o `SUPER`). Los scripts no fijan `DEFINER` para admitir ambas.

## 3. Procedimiento de ejecución (paso 17)

1. **Respaldo previo** (aunque la base sea nueva, respaldar la instancia si es compartida — P-DBMS-02).
2. Cargar `.env` en el entorno de la shell de despliegue (no en archivos versionados).
3. Para scripts sin marcadores: `mysql --defaults-extra-file=<archivo temporal 0600 con credenciales> < V00X.sql`.
4. Para V002, V008 y V010 (con marcadores): sustitución en memoria y tubería, sin escribir el resultado:
   `envsubst '${PLACEHOLDERS_SUSTITUIDOS} ${SIS_MIGRADOR_PASSWORD} ...' < V008__crear_roles_y_cuentas.sql | mysql ...`
   con `PLACEHOLDERS_SUSTITUIDOS=SI` exportada. Si el script se ejecuta sin sustituir, su bloque de verificación inicial aborta antes de crear cuentas.
5. El cliente `mysql` en modo no interactivo **se detiene en el primer error**: no usar `--force`.
6. Tras cada script, ejecutar la verificación de §6 y comprobar la fila en `cfg_version_esquema`.
7. Al terminar: `ALTER USER sis_migrador ACCOUNT LOCK` (paso 09 §2) y `ANALYZE TABLE` de las tablas con datos.

## 4. Reversión

Cada `V###` tiene `rollback/R###`. Se ejecutan en **orden inverso** y solo hasta la versión deseada.

| Script de reversión | Acción | Naturaleza | Autorización |
|---|---|---|---|
| `R010` | Quita roles y borra el administrador inicial y su área | Datos; solo válido antes de usar el sistema (las FK lo impiden después) | Explícita |
| `R009` | Borra los datos de catálogos y configuración | Datos; las FK `RESTRICT` lo bloquean si ya hay solicitudes o usuarios | Explícita |
| `R008` | Revoca y elimina cuentas y roles de servicio | Seguridad | Explícita |
| `R007` | Elimina triggers de auditoría | Estructura (no borra datos) | Normal |
| `R006` | Elimina triggers de integridad | Estructura (no borra datos) | Normal |
| `R005` | Elimina las FK | Estructura (no borra datos) | Normal |
| `R004` | Elimina índices secundarios | Estructura (no borra datos) | Normal |
| `R003` | **`DROP TABLE`** de las 20 tablas de negocio | **Destructiva** | **Explícita y por escrito** (workflow paso 17) |
| `R002` | Elimina la cuenta `sis_migrador` | Seguridad | Explícita |
| `R001` | **`DROP DATABASE`** | **Destructiva** | **Explícita y por escrito** |

Cada `R###` (salvo R001) termina borrando su fila de `cfg_version_esquema`. Los scripts R003 y R001 comienzan con un comentario de advertencia y **no** se ejecutan como parte de ningún procedimiento automático.

**Ejecución parcial (DDL no transaccional):** si un `V###` falla a mitad, el esquema queda con parte de los objetos y **sin** la fila de versión. Procedimiento: (1) identificar con `99_verificacion.sql` qué objetos existen; (2) ejecutar el `R###` correspondiente — está escrito con `DROP ... IF EXISTS` / `DROP TRIGGER IF EXISTS` para tolerar objetos ausentes; (3) corregir y volver a ejecutar el `V###`. Las reversiones sí usan `IF EXISTS` porque son operaciones de recuperación que deben converger (skill `databases`: "rerunnable operational scripts converge").

## 5. Migraciones futuras (después de la v1)

| Cambio | Técnica en MySQL 8.4 |
|---|---|
| Añadir columna nula o con `DEFAULT` constante | `ALTER TABLE ... ADD COLUMN ..., ALGORITHM=INSTANT` |
| Añadir índice | `ALTER TABLE ... ADD INDEX ..., ALGORITHM=INPLACE, LOCK=NONE` |
| Columna `NOT NULL` nueva sin valor por defecto | Expandir (nula) → rellenar por lotes de ≤ 5 000 filas → `MODIFY ... NOT NULL` |
| Renombrar columna | Expandir/contraer: columna nueva, doble escritura, migrar lectura, eliminar la antigua en una versión posterior |
| Cambiar un `CHECK` | `ALTER TABLE ... DROP CHECK x, ADD CONSTRAINT x CHECK (...)` en el mismo `ALTER` (valida las filas existentes) |
| Activar T10 (S-10) | `UPDATE cat_transicion_estado SET activa = 1 WHERE id = 10` en un `V###` nuevo, previa aprobación humana; queda auditado |
| Nuevo valor de dominio (p. ej. tipo de notificación) | Reemplazar el `CHECK` correspondiente; desplegar la aplicación después |
| Cambiar ids de `cat_estado` | **No permitido** sin rediseñar los `CHECK` de `sol_solicitud` (paso 08 §3) |

Ventana: las migraciones con bloqueo se ejecutan fuera del horario laboral (08:30–16:30, `perfil_carga.yaml`; RNF-03).

## 6. Verificación (`99_verificacion.sql`, solo lectura)

1. Versión aplicada: filas de `cfg_version_esquema`.
2. Charset/collation de la base y de cada tabla; motor `InnoDB` en todas.
3. Conteo de tablas (21), `CHECK` (`information_schema.CHECK_CONSTRAINTS`), FK (`REFERENTIAL_CONSTRAINTS`, todas `RESTRICT`), índices (`STATISTICS`) y triggers (`TRIGGERS`).
4. FK sin índice propio: consulta que debe devolver cero filas.
5. Conteos de catálogos: 4 roles, 7 estados, 10 transiciones (9 activas), 4 prioridades, 10 tipos de archivo, 7 días de horario (5 activos), 3 parámetros.
6. `sql_mode` contiene `STRICT_TRANS_TABLES`; `transaction_isolation`; `require_secure_transport`.

## 7. Riesgos del plan

| Riesgo | Mitigación |
|---|---|
| DDL no transaccional deja el esquema a medias | Scripts pequeños; reversión tolerante; verificación tras cada script |
| Ejecutar un script dos veces | PK de `cfg_version_esquema` + `CREATE TABLE` sin `IF NOT EXISTS` ⇒ falla y no duplica |
| Crear cuentas con marcadores sin sustituir | Bloque de verificación inicial que aborta |
| Contraseñas en el historial o en disco | Ejecución por tubería; archivo de credenciales temporal con permisos 0600 y borrado al terminar |
| Privilegios para crear triggers con binlog | §2, opciones (a)/(b) |
| Zona horaria no definida (P-FIS-01) | No afecta a la estructura; se fija en la conexión antes de que la aplicación escriba datos |

## 8. Siguiente paso

Paso 14 — Revisión DBA (`15_reportes/revision_dba.md`).
