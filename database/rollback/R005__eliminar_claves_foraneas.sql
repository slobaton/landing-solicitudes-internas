-- =============================================================================
-- R005__eliminar_claves_foraneas.sql   (reversion de V005)
-- Ejecuta: sis_migrador. No borra datos, pero elimina la integridad
-- referencial: no ejecutar con el sistema en uso.
-- MySQL no admite DROP FOREIGN KEY IF EXISTS: se usa un procedimiento
-- temporal que solo elimina las FK existentes (re-ejecutable).
-- =============================================================================

USE sis_solicitudes;

DROP PROCEDURE IF EXISTS tmp_eliminar_fk;
DELIMITER //
CREATE PROCEDURE tmp_eliminar_fk(IN p_tabla VARCHAR(64), IN p_fk VARCHAR(64))
BEGIN
  IF EXISTS (SELECT 1
               FROM information_schema.TABLE_CONSTRAINTS
              WHERE constraint_schema = DATABASE()
                AND table_name        = p_tabla
                AND constraint_name   = p_fk
                AND constraint_type   = 'FOREIGN KEY') THEN
    SET @tmp_sql = CONCAT('ALTER TABLE `', p_tabla, '` DROP FOREIGN KEY `', p_fk, '`');
    PREPARE tmp_stmt FROM @tmp_sql;
    EXECUTE tmp_stmt;
    DEALLOCATE PREPARE tmp_stmt;
  END IF;
END//
DELIMITER ;

CALL tmp_eliminar_fk('aud_auditoria',         'fk_aud_auditoria_usuario');
CALL tmp_eliminar_fk('cfg_parametro',         'fk_cfg_parametro_actualizado_por');
CALL tmp_eliminar_fk('not_correo',            'fk_not_correo_notificacion');
CALL tmp_eliminar_fk('not_notificacion',      'fk_not_notificacion_usuario');
CALL tmp_eliminar_fk('not_notificacion',      'fk_not_notificacion_solicitud');
CALL tmp_eliminar_fk('sol_adjunto',           'fk_sol_adjunto_solicitud');
CALL tmp_eliminar_fk('sol_adjunto',           'fk_sol_adjunto_usuario');
CALL tmp_eliminar_fk('sol_adjunto',           'fk_sol_adjunto_tipo_archivo');
CALL tmp_eliminar_fk('sol_comentario',        'fk_sol_comentario_solicitud');
CALL tmp_eliminar_fk('sol_comentario',        'fk_sol_comentario_usuario');
CALL tmp_eliminar_fk('sol_historial',         'fk_sol_historial_solicitud');
CALL tmp_eliminar_fk('sol_historial',         'fk_sol_historial_usuario');
CALL tmp_eliminar_fk('sol_historial',         'fk_sol_historial_transicion');
CALL tmp_eliminar_fk('sol_historial',         'fk_sol_historial_estado_anterior');
CALL tmp_eliminar_fk('sol_historial',         'fk_sol_historial_estado_nuevo');
CALL tmp_eliminar_fk('sol_historial',         'fk_sol_historial_resp_anterior');
CALL tmp_eliminar_fk('sol_historial',         'fk_sol_historial_resp_nuevo');
CALL tmp_eliminar_fk('sol_historial',         'fk_sol_historial_prio_anterior');
CALL tmp_eliminar_fk('sol_historial',         'fk_sol_historial_prio_nueva');
CALL tmp_eliminar_fk('sol_solicitud',         'fk_sol_solicitud_solicitante');
CALL tmp_eliminar_fk('sol_solicitud',         'fk_sol_solicitud_area_destino');
CALL tmp_eliminar_fk('sol_solicitud',         'fk_sol_solicitud_tipo');
CALL tmp_eliminar_fk('sol_solicitud',         'fk_sol_solicitud_prioridad');
CALL tmp_eliminar_fk('sol_solicitud',         'fk_sol_solicitud_estado');
CALL tmp_eliminar_fk('sol_solicitud',         'fk_sol_solicitud_responsable');
CALL tmp_eliminar_fk('cat_transicion_estado', 'fk_cat_transicion_origen');
CALL tmp_eliminar_fk('cat_transicion_estado', 'fk_cat_transicion_destino');
CALL tmp_eliminar_fk('cat_transicion_estado', 'fk_cat_transicion_rol');
CALL tmp_eliminar_fk('org_area',              'fk_org_area_jefe');
CALL tmp_eliminar_fk('seg_usuario_rol',       'fk_seg_usuario_rol_usuario');
CALL tmp_eliminar_fk('seg_usuario_rol',       'fk_seg_usuario_rol_rol');
CALL tmp_eliminar_fk('seg_usuario_rol',       'fk_seg_usuario_rol_asignado_por');
CALL tmp_eliminar_fk('seg_usuario',           'fk_seg_usuario_area');

DROP PROCEDURE tmp_eliminar_fk;

DELETE FROM cfg_version_esquema WHERE version = 'V005';
