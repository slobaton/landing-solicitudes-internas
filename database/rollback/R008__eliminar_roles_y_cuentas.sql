-- =============================================================================
-- R008__eliminar_roles_y_cuentas.sql   (reversion de V008)
-- Ejecuta: cuenta DBA institucional. REQUIERE AUTORIZACION EXPLICITA
-- (la aplicacion, el cron, los reportes y el respaldo dejan de conectar).
--
-- CONTIENE MARCADORES (sustituir con envsubst; ver plan_migraciones.md §3).
-- Variables: PLACEHOLDERS_SUSTITUIDOS, SIS_APP_HOST, SIS_REPORTE_HOST,
--            SIS_RESPALDO_HOST
-- Re-ejecutable: usa IF EXISTS. Al eliminar una cuenta o un rol se eliminan
-- tambien sus privilegios.
-- =============================================================================

USE sis_solicitudes;

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

DROP USER IF EXISTS
  'sis_app'@'${SIS_APP_HOST}',
  'sis_cron'@'${SIS_APP_HOST}',
  'sis_reporte'@'${SIS_REPORTE_HOST}',
  'sis_respaldo'@'${SIS_RESPALDO_HOST}';

DROP ROLE IF EXISTS 'sis_rol_app', 'sis_rol_lectura', 'sis_rol_respaldo';

DELETE FROM cfg_version_esquema WHERE version = 'V008';
