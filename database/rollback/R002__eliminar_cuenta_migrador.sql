-- =============================================================================
-- R002__eliminar_cuenta_migrador.sql   (reversion de V002)
-- Ejecuta: cuenta DBA institucional. REQUIERE AUTORIZACION EXPLICITA.
-- Aplicar R006 y R007 antes: sis_migrador es el DEFINER de los triggers y,
-- si se elimina con triggers existentes, estos fallarian al ejecutarse.
--
-- CONTIENE MARCADORES (sustituir con envsubst; ver plan_migraciones.md §3).
-- Variables: PLACEHOLDERS_SUSTITUIDOS, SIS_MIGRADOR_HOST
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
  IF EXISTS (SELECT 1 FROM information_schema.TRIGGERS
              WHERE trigger_schema = DATABASE()
                AND definer LIKE 'sis_migrador@%') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Existen triggers con DEFINER sis_migrador: aplicar R006 y R007 antes';
  END IF;
END//
DELIMITER ;
CALL tmp_verificar_marcadores('${PLACEHOLDERS_SUSTITUIDOS}');
DROP PROCEDURE tmp_verificar_marcadores;

DROP USER IF EXISTS 'sis_migrador'@'${SIS_MIGRADOR_HOST}';

DELETE FROM cfg_version_esquema WHERE version = 'V002';
