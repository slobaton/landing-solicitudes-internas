-- =============================================================================
-- R006__eliminar_triggers_integridad.sql   (reversion de V006)
-- Ejecuta: sis_migrador. No borra datos, pero elimina las reglas TRG-01..TRG-10
-- (incluida la inmutabilidad de historial y auditoria): no ejecutar con el
-- sistema en uso.
-- Re-ejecutable: DROP TRIGGER IF EXISTS.
-- =============================================================================

USE sis_solicitudes;

DROP TRIGGER IF EXISTS trg_sol_solicitud_bi;
DROP TRIGGER IF EXISTS trg_sol_solicitud_bu;
DROP TRIGGER IF EXISTS trg_sol_historial_bi;
DROP TRIGGER IF EXISTS trg_sol_historial_bu;
DROP TRIGGER IF EXISTS trg_sol_historial_bd;
DROP TRIGGER IF EXISTS trg_sol_comentario_bu;
DROP TRIGGER IF EXISTS trg_sol_comentario_bd;
DROP TRIGGER IF EXISTS trg_sol_adjunto_bi;
DROP TRIGGER IF EXISTS trg_sol_adjunto_bu;
DROP TRIGGER IF EXISTS trg_sol_adjunto_bd;
DROP TRIGGER IF EXISTS trg_aud_auditoria_bu;
DROP TRIGGER IF EXISTS trg_aud_auditoria_bd;
DROP TRIGGER IF EXISTS trg_org_area_bi;
DROP TRIGGER IF EXISTS trg_org_area_bu;
DROP TRIGGER IF EXISTS trg_seg_usuario_bu;
DROP TRIGGER IF EXISTS trg_seg_usuario_rol_bd;

DELETE FROM cfg_version_esquema WHERE version = 'V006';
