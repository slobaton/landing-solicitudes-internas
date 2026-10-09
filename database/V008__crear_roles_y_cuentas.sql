-- =============================================================================
-- V008__crear_roles_y_cuentas.sql
-- Sistema de seguimiento de solicitudes internas
-- Ejecuta: cuenta DBA institucional (CREATE ROLE, CREATE USER, GRANT OPTION)
-- Fuente: 07_seguridad/seguridad.md §2-§5 (matriz de privilegios)
--
-- CONTIENE MARCADORES. NO EJECUTAR DIRECTAMENTE.
-- Sustituir en memoria desde .env con envsubst (lista explicita de variables)
-- y enviar por tuberia al cliente mysql. Nunca guardar ni versionar el archivo
-- sustituido. Procedimiento: 12_migraciones/plan_migraciones.md §3.
-- (Los comentarios no contienen marcadores a proposito.)
--
-- Variables de entorno requeridas:
--   PLACEHOLDERS_SUSTITUIDOS   debe valer SI
--   SIS_APP_HOST               host del servidor web (app y cron)
--   SIS_APP_PASSWORD           contrasena de sis_app
--   SIS_CRON_PASSWORD          contrasena de sis_cron
--   SIS_REPORTE_HOST           host de reportes
--   SIS_REPORTE_PASSWORD       contrasena de sis_reporte
--   SIS_RESPALDO_HOST          host de respaldo
--   SIS_RESPALDO_PASSWORD      contrasena de sis_respaldo
-- Contrasenas generadas aleatoriamente, >= 16 caracteres.
-- =============================================================================

USE sis_solicitudes;

-- Proteccion: aborta si los marcadores no fueron sustituidos.
DROP PROCEDURE IF EXISTS tmp_verificar_marcadores;
DELIMITER //
CREATE PROCEDURE tmp_verificar_marcadores(IN p_valor VARCHAR(40))
BEGIN
  IF p_valor IS NULL OR p_valor <> 'SI' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Marcadores sin sustituir: ejecutar con envsubst (ver plan_migraciones.md)';
  END IF;
END//
DELIMITER ;
CALL tmp_verificar_marcadores('${PLACEHOLDERS_SUSTITUIDOS}');
DROP PROCEDURE tmp_verificar_marcadores;

-- -----------------------------------------------------------------------------
-- 1. Roles (operacion re-ejecutable: IF NOT EXISTS admitido, R-V06)
-- -----------------------------------------------------------------------------
CREATE ROLE IF NOT EXISTS 'sis_rol_app', 'sis_rol_lectura', 'sis_rol_respaldo';

-- -----------------------------------------------------------------------------
-- 2. sis_rol_app: DML minimo (seguridad.md §3)
-- -----------------------------------------------------------------------------
GRANT SELECT, INSERT, UPDATE         ON sis_solicitudes.seg_usuario            TO 'sis_rol_app';
GRANT SELECT                         ON sis_solicitudes.seg_rol                TO 'sis_rol_app';
GRANT SELECT, INSERT, DELETE         ON sis_solicitudes.seg_usuario_rol        TO 'sis_rol_app';
GRANT SELECT, INSERT, UPDATE         ON sis_solicitudes.org_area               TO 'sis_rol_app';
GRANT SELECT, INSERT, UPDATE         ON sis_solicitudes.cat_tipo_solicitud     TO 'sis_rol_app';
GRANT SELECT, UPDATE                 ON sis_solicitudes.cat_prioridad          TO 'sis_rol_app';
GRANT SELECT                         ON sis_solicitudes.cat_estado             TO 'sis_rol_app';
GRANT SELECT                         ON sis_solicitudes.cat_transicion_estado  TO 'sis_rol_app';
GRANT SELECT, INSERT, UPDATE         ON sis_solicitudes.sol_secuencia          TO 'sis_rol_app';
GRANT SELECT, INSERT, UPDATE         ON sis_solicitudes.sol_solicitud          TO 'sis_rol_app';
GRANT SELECT, INSERT                 ON sis_solicitudes.sol_historial          TO 'sis_rol_app';
GRANT SELECT, INSERT                 ON sis_solicitudes.sol_comentario         TO 'sis_rol_app';
GRANT SELECT, INSERT                 ON sis_solicitudes.sol_adjunto            TO 'sis_rol_app';
GRANT SELECT, INSERT, UPDATE         ON sis_solicitudes.not_notificacion       TO 'sis_rol_app';
GRANT SELECT, INSERT, UPDATE         ON sis_solicitudes.not_correo             TO 'sis_rol_app';
GRANT SELECT, UPDATE                 ON sis_solicitudes.cfg_horario_laboral    TO 'sis_rol_app';
GRANT SELECT, INSERT, UPDATE, DELETE ON sis_solicitudes.cfg_feriado            TO 'sis_rol_app';
GRANT SELECT, INSERT, UPDATE         ON sis_solicitudes.cfg_tipo_archivo       TO 'sis_rol_app';
GRANT SELECT, UPDATE                 ON sis_solicitudes.cfg_parametro          TO 'sis_rol_app';
GRANT SELECT, INSERT                 ON sis_solicitudes.aud_auditoria          TO 'sis_rol_app';
GRANT SELECT                         ON sis_solicitudes.cfg_version_esquema    TO 'sis_rol_app';

-- -----------------------------------------------------------------------------
-- 3. sis_rol_lectura: solo SELECT, sin password_hash (seguridad.md §3)
-- -----------------------------------------------------------------------------
GRANT SELECT (id, nombre_usuario, nombres, apellidos, correo, area_id, activo,
              ultimo_acceso_en, creado_en, actualizado_en)
                       ON sis_solicitudes.seg_usuario            TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.seg_rol                TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.seg_usuario_rol        TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.org_area               TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.cat_tipo_solicitud     TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.cat_prioridad          TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.cat_estado             TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.cat_transicion_estado  TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.sol_secuencia          TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.sol_solicitud          TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.sol_historial          TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.sol_comentario         TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.sol_adjunto            TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.not_notificacion       TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.not_correo             TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.cfg_horario_laboral    TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.cfg_feriado            TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.cfg_tipo_archivo       TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.cfg_parametro          TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.aud_auditoria          TO 'sis_rol_lectura';
GRANT SELECT           ON sis_solicitudes.cfg_version_esquema    TO 'sis_rol_lectura';

-- -----------------------------------------------------------------------------
-- 4. sis_rol_respaldo: volcado logico consistente (seguridad.md §3, §9)
-- -----------------------------------------------------------------------------
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES, EVENT ON sis_solicitudes.* TO 'sis_rol_respaldo';
GRANT PROCESS, RELOAD, REPLICATION CLIENT ON *.* TO 'sis_rol_respaldo';

-- -----------------------------------------------------------------------------
-- 5. Cuentas de servicio (operacion re-ejecutable)
--    sis_app / sis_cron sin bloqueo por intentos (evita denegacion de servicio
--    por error de configuracion, seguridad.md §4).
-- -----------------------------------------------------------------------------
CREATE USER IF NOT EXISTS 'sis_app'@'${SIS_APP_HOST}'
  IDENTIFIED WITH caching_sha2_password BY '${SIS_APP_PASSWORD}'
  REQUIRE SSL
  WITH MAX_USER_CONNECTIONS 80;

CREATE USER IF NOT EXISTS 'sis_cron'@'${SIS_APP_HOST}'
  IDENTIFIED WITH caching_sha2_password BY '${SIS_CRON_PASSWORD}'
  REQUIRE SSL
  WITH MAX_USER_CONNECTIONS 5;

CREATE USER IF NOT EXISTS 'sis_reporte'@'${SIS_REPORTE_HOST}'
  IDENTIFIED WITH caching_sha2_password BY '${SIS_REPORTE_PASSWORD}'
  REQUIRE SSL
  WITH MAX_USER_CONNECTIONS 5
  FAILED_LOGIN_ATTEMPTS 5 PASSWORD_LOCK_TIME 1;

CREATE USER IF NOT EXISTS 'sis_respaldo'@'${SIS_RESPALDO_HOST}'
  IDENTIFIED WITH caching_sha2_password BY '${SIS_RESPALDO_PASSWORD}'
  REQUIRE SSL
  WITH MAX_USER_CONNECTIONS 2
  FAILED_LOGIN_ATTEMPTS 5 PASSWORD_LOCK_TIME 1;

GRANT 'sis_rol_app'      TO 'sis_app'@'${SIS_APP_HOST}';
GRANT 'sis_rol_app'      TO 'sis_cron'@'${SIS_APP_HOST}';
GRANT 'sis_rol_lectura'  TO 'sis_reporte'@'${SIS_REPORTE_HOST}';
GRANT 'sis_rol_respaldo' TO 'sis_respaldo'@'${SIS_RESPALDO_HOST}';

SET DEFAULT ROLE 'sis_rol_app'      TO 'sis_app'@'${SIS_APP_HOST}';
SET DEFAULT ROLE 'sis_rol_app'      TO 'sis_cron'@'${SIS_APP_HOST}';
SET DEFAULT ROLE 'sis_rol_lectura'  TO 'sis_reporte'@'${SIS_REPORTE_HOST}';
SET DEFAULT ROLE 'sis_rol_respaldo' TO 'sis_respaldo'@'${SIS_RESPALDO_HOST}';

INSERT INTO cfg_version_esquema (version, descripcion, script, aplicado_por)
VALUES ('V008', 'Crear roles, privilegios por tabla y cuentas de servicio',
        'V008__crear_roles_y_cuentas.sql', CURRENT_USER());
