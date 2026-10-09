-- =============================================================================
-- R011__eliminar_tipos_y_areas.sql   (reversion de V011)
-- Ejecuta: cuenta DBA. REQUIERE AUTORIZACION EXPLICITA (borra datos).
-- Solo aplicable antes de registrar solicitudes o usuarios en estas areas:
-- las FK RESTRICT bloquean el borrado y la transaccion se revierte completa.
-- Si ya hay datos, usar inactivacion logica (activo = 0) en lugar de este script.
-- =============================================================================

USE sis_solicitudes;

SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;

SET @sis_usuario_id = NULL;
SET @sis_ip = NULL;

START TRANSACTION;

DELETE FROM org_area WHERE codigo IN ('MANT', 'ADM', 'ALM');

DELETE FROM cat_tipo_solicitud
 WHERE nombre IN ('Soporte técnico', 'Solicitud de materiales',
                  'Mantenimiento', 'Trámite administrativo');

DELETE FROM cfg_version_esquema WHERE version = 'V011';

COMMIT;
