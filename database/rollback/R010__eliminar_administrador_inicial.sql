-- =============================================================================
-- R010__eliminar_administrador_inicial.sql   (reversion de V010)
-- Ejecuta: sis_migrador. REQUIERE AUTORIZACION EXPLICITA (borra datos).
-- Solo aplicable antes de usar el sistema: si el administrador ya inicio
-- sesion o creo datos, las FK RESTRICT (p. ej. aud_auditoria.usuario_id)
-- bloquean el borrado y la transaccion se revierte completa.
--
-- CONTIENE MARCADORES (sustituir con envsubst; ver plan_migraciones.md §3).
-- Variables: PLACEHOLDERS_SUSTITUIDOS, ADMIN_NOMBRE_USUARIO, ADMIN_AREA_CODIGO
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

SET @sis_usuario_id = NULL;
SET @sis_ip = NULL;

START TRANSACTION;

DELETE ur
  FROM seg_usuario_rol ur
  JOIN seg_usuario u ON u.id = ur.usuario_id
 WHERE u.nombre_usuario = '${ADMIN_NOMBRE_USUARIO}';

DELETE FROM seg_usuario WHERE nombre_usuario = '${ADMIN_NOMBRE_USUARIO}';

DELETE FROM org_area WHERE codigo = '${ADMIN_AREA_CODIGO}';

DELETE FROM cfg_version_esquema WHERE version = 'V010';

COMMIT;
