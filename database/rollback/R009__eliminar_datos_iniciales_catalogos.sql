-- =============================================================================
-- R009__eliminar_datos_iniciales_catalogos.sql   (reversion de V009)
-- Ejecuta: sis_migrador. REQUIERE AUTORIZACION EXPLICITA (borra datos).
-- Aplicar R010 antes si se ejecuto V010.
-- Las FK RESTRICT actuan como proteccion: si ya existen usuarios, solicitudes
-- o historial que usen estos catalogos, el primer DELETE afectado falla y la
-- transaccion se revierte completa.
-- Los registros de aud_auditoria de la carga inicial permanecen (inmutables).
-- =============================================================================

USE sis_solicitudes;

SET @sis_usuario_id = NULL;
SET @sis_ip = NULL;

START TRANSACTION;

DELETE FROM cat_transicion_estado WHERE id BETWEEN 1 AND 10;
DELETE FROM cat_prioridad         WHERE id BETWEEN 1 AND 4;
DELETE FROM cat_estado            WHERE id BETWEEN 1 AND 7;
DELETE FROM seg_rol               WHERE id BETWEEN 1 AND 4;
DELETE FROM cfg_tipo_archivo
 WHERE extension IN ('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx');
DELETE FROM cfg_horario_laboral   WHERE dia_semana BETWEEN 1 AND 7;
DELETE FROM cfg_parametro
 WHERE clave IN ('ADJUNTO_TAMANO_MAX_MB', 'CIERRE_AUTOMATICO_DIAS_HABILES', 'CORREO_MAX_INTENTOS');

DELETE FROM cfg_version_esquema WHERE version = 'V009';

COMMIT;
