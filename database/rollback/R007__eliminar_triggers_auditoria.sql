-- =============================================================================
-- R007__eliminar_triggers_auditoria.sql   (reversion de V007)
-- Ejecuta: sis_migrador. No borra datos (los registros de aud_auditoria
-- permanecen), pero deja SIN auditoria administrativa a la aplicacion:
-- no ejecutar con el sistema en uso.
-- Re-ejecutable: DROP TRIGGER IF EXISTS.
-- =============================================================================

USE sis_solicitudes;

DROP TRIGGER IF EXISTS trg_seg_usuario_aud_ai;
DROP TRIGGER IF EXISTS trg_seg_usuario_aud_au;
DROP TRIGGER IF EXISTS trg_seg_usuario_rol_aud_ai;
DROP TRIGGER IF EXISTS trg_seg_usuario_rol_aud_ad;
DROP TRIGGER IF EXISTS trg_org_area_aud_ai;
DROP TRIGGER IF EXISTS trg_org_area_aud_au;
DROP TRIGGER IF EXISTS trg_cat_tipo_solicitud_aud_ai;
DROP TRIGGER IF EXISTS trg_cat_tipo_solicitud_aud_au;
DROP TRIGGER IF EXISTS trg_cat_prioridad_aud_ai;
DROP TRIGGER IF EXISTS trg_cat_prioridad_aud_au;
DROP TRIGGER IF EXISTS trg_cat_transicion_estado_aud_au;
DROP TRIGGER IF EXISTS trg_cfg_tipo_archivo_aud_ai;
DROP TRIGGER IF EXISTS trg_cfg_tipo_archivo_aud_au;
DROP TRIGGER IF EXISTS trg_cfg_parametro_aud_ai;
DROP TRIGGER IF EXISTS trg_cfg_parametro_aud_au;
DROP TRIGGER IF EXISTS trg_cfg_horario_laboral_aud_ai;
DROP TRIGGER IF EXISTS trg_cfg_horario_laboral_aud_au;
DROP TRIGGER IF EXISTS trg_cfg_feriado_aud_ai;
DROP TRIGGER IF EXISTS trg_cfg_feriado_aud_au;
DROP TRIGGER IF EXISTS trg_cfg_feriado_aud_ad;

DELETE FROM cfg_version_esquema WHERE version = 'V007';
