-- =============================================================================
-- 99_verificacion.sql   (SOLO LECTURA)
-- Sistema de seguimiento de solicitudes internas
-- Ejecuta: cualquier cuenta con SELECT sobre information_schema y la base
-- Fuente: 12_migraciones/plan_migraciones.md §6
-- No modifica datos ni estructura. Puede ejecutarse tras cada script o al final.
-- =============================================================================

USE sis_solicitudes;

-- 1. Versiones aplicadas -------------------------------------------------------
SELECT version, descripcion, aplicado_en, aplicado_por
  FROM cfg_version_esquema
 ORDER BY version;

-- 2. Juego de caracteres de la base y motor/collation de cada tabla ------------
SELECT default_character_set_name, default_collation_name
  FROM information_schema.SCHEMATA
 WHERE schema_name = 'sis_solicitudes';

SELECT table_name, engine, table_collation
  FROM information_schema.TABLES
 WHERE table_schema = 'sis_solicitudes'
   AND (engine <> 'InnoDB' OR table_collation <> 'utf8mb4_0900_ai_ci');   -- esperado: 0 filas

-- 3. Conteo de objetos ---------------------------------------------------------
SELECT 'tablas' AS objeto, COUNT(*) AS total, 21 AS esperado
  FROM information_schema.TABLES
 WHERE table_schema = 'sis_solicitudes' AND table_type = 'BASE TABLE'
UNION ALL
SELECT 'claves_foraneas', COUNT(*), 33
  FROM information_schema.REFERENTIAL_CONSTRAINTS
 WHERE constraint_schema = 'sis_solicitudes'
UNION ALL
SELECT 'triggers', COUNT(*), 36   -- 16 de integridad (V006) + 20 de auditoria (V007)
  FROM information_schema.TRIGGERS
 WHERE trigger_schema = 'sis_solicitudes'
UNION ALL
SELECT 'check_constraints', COUNT(*), NULL
  FROM information_schema.CHECK_CONSTRAINTS
 WHERE constraint_schema = 'sis_solicitudes';

-- 4. Toda FK debe ser RESTRICT -------------------------------------------------
SELECT constraint_name, table_name, delete_rule, update_rule
  FROM information_schema.REFERENTIAL_CONSTRAINTS
 WHERE constraint_schema = 'sis_solicitudes'
   AND (delete_rule <> 'RESTRICT' OR update_rule <> 'RESTRICT');         -- esperado: 0 filas

-- 5. FK sin indice cuyo primer campo sea la primera columna de la FK ----------
SELECT k.table_name, k.constraint_name, k.column_name
  FROM information_schema.KEY_COLUMN_USAGE k
 WHERE k.table_schema = 'sis_solicitudes'
   AND k.referenced_table_name IS NOT NULL
   AND k.ordinal_position = 1
   AND NOT EXISTS (SELECT 1
                     FROM information_schema.STATISTICS s
                    WHERE s.table_schema = k.table_schema
                      AND s.table_name   = k.table_name
                      AND s.column_name  = k.column_name
                      AND s.seq_in_index = 1);                            -- esperado: 0 filas

-- 6. Indices por tabla (comparar con indices_rendimiento.md §4) ----------------
SELECT table_name, index_name,
       GROUP_CONCAT(column_name ORDER BY seq_in_index) AS columnas,
       MIN(non_unique) AS no_unico
  FROM information_schema.STATISTICS
 WHERE table_schema = 'sis_solicitudes'
 GROUP BY table_name, index_name
 ORDER BY table_name, index_name;

-- 7. Triggers ------------------------------------------------------------------
SELECT event_object_table, trigger_name, action_timing, event_manipulation, definer
  FROM information_schema.TRIGGERS
 WHERE trigger_schema = 'sis_solicitudes'
 ORDER BY event_object_table, action_timing, event_manipulation;

-- 8. Catalogos (tras V009) -----------------------------------------------------
SELECT 'seg_rol' AS catalogo, COUNT(*) AS total, 4 AS esperado FROM seg_rol
UNION ALL SELECT 'cat_estado', COUNT(*), 7 FROM cat_estado
UNION ALL SELECT 'cat_estado finales', COUNT(*), 3 FROM cat_estado WHERE es_final = 1
UNION ALL SELECT 'cat_transicion_estado', COUNT(*), 10 FROM cat_transicion_estado
UNION ALL SELECT 'cat_transicion_estado activas', COUNT(*), 9 FROM cat_transicion_estado WHERE activa = 1
UNION ALL SELECT 'cat_prioridad', COUNT(*), 4 FROM cat_prioridad
UNION ALL SELECT 'cfg_tipo_archivo', COUNT(*), 10 FROM cfg_tipo_archivo
UNION ALL SELECT 'cfg_horario_laboral', COUNT(*), 7 FROM cfg_horario_laboral
UNION ALL SELECT 'cfg_horario_laboral activos', COUNT(*), 5 FROM cfg_horario_laboral WHERE activo = 1
UNION ALL SELECT 'cfg_parametro', COUNT(*), 3 FROM cfg_parametro;

-- 9. Configuracion del servidor y de la sesion ---------------------------------
SELECT @@GLOBAL.sql_mode                AS sql_mode_global,
       @@GLOBAL.sql_mode LIKE '%STRICT_TRANS_TABLES%' AS modo_estricto,
       @@GLOBAL.transaction_isolation   AS aislamiento_global,
       @@SESSION.transaction_isolation  AS aislamiento_sesion,
       @@GLOBAL.require_secure_transport AS tls_obligatorio,
       @@GLOBAL.character_set_server    AS charset_servidor,
       @@GLOBAL.local_infile            AS local_infile,
       @@GLOBAL.log_bin                 AS binlog,
       @@GLOBAL.binlog_format           AS binlog_formato,
       @@GLOBAL.time_zone               AS zona_global,
       @@SESSION.time_zone              AS zona_sesion,
       VERSION()                        AS version_mysql;

-- 10. Conciliaciones de integridad (integridad.md §8), esperado: 0 filas -------
-- C-01: estado actual vs ultimo evento de estado del historial
SELECT s.id, s.numero, s.estado_id, h.estado_nuevo_id
  FROM sol_solicitud s
  JOIN sol_historial h
    ON h.id = (SELECT MAX(h2.id)
                 FROM sol_historial h2
                WHERE h2.solicitud_id = s.id
                  AND h2.tipo_evento IN ('REGISTRO', 'CAMBIO_ESTADO'))
 WHERE h.estado_nuevo_id <> s.estado_id;

-- C-02: solicitudes sin evento REGISTRO
SELECT s.id, s.numero
  FROM sol_solicitud s
 WHERE NOT EXISTS (SELECT 1 FROM sol_historial h
                    WHERE h.solicitud_id = s.id AND h.tipo_evento = 'REGISTRO');

-- C-03: cambios de estado hechos por un usuario sin el rol de la transicion
SELECT h.id, h.solicitud_id, h.usuario_id, t.rol_id
  FROM sol_historial h
  JOIN cat_transicion_estado t ON t.id = h.transicion_id
 WHERE t.rol_id IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM seg_usuario_rol ur
                    WHERE ur.usuario_id = h.usuario_id AND ur.rol_id = t.rol_id);

-- C-04: responsable fuera del area destino
SELECT s.id, s.numero, s.responsable_id, u.area_id, s.area_destino_id
  FROM sol_solicitud s
  JOIN seg_usuario u ON u.id = s.responsable_id
 WHERE u.area_id <> s.area_destino_id;

-- C-05: cancelaciones (T03, T06) no hechas por el solicitante
SELECT h.id, h.solicitud_id, h.usuario_id, s.solicitante_id
  FROM sol_historial h
  JOIN sol_solicitud s ON s.id = h.solicitud_id
 WHERE h.transicion_id IN (3, 6)
   AND NOT (h.usuario_id <=> s.solicitante_id);

-- C-06: jefes de area sin rol JEFE_AREA
SELECT a.id, a.codigo, a.jefe_usuario_id
  FROM org_area a
 WHERE a.jefe_usuario_id IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM seg_usuario_rol ur
                    WHERE ur.usuario_id = a.jefe_usuario_id AND ur.rol_id = 2);
