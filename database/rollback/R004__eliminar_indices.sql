-- =============================================================================
-- R004__eliminar_indices.sql   (reversion de V004)
-- Ejecuta: sis_migrador. Aplicar R005 antes (InnoDB no permite eliminar un
-- indice que soporta una FK). No borra datos.
-- MySQL no admite DROP INDEX IF EXISTS: se usa un procedimiento temporal
-- que solo elimina los indices existentes (re-ejecutable).
-- =============================================================================

USE sis_solicitudes;

DROP PROCEDURE IF EXISTS tmp_eliminar_indice;
DELIMITER //
CREATE PROCEDURE tmp_eliminar_indice(IN p_tabla VARCHAR(64), IN p_indice VARCHAR(64))
BEGIN
  IF EXISTS (SELECT 1
               FROM information_schema.STATISTICS
              WHERE table_schema = DATABASE()
                AND table_name   = p_tabla
                AND index_name   = p_indice) THEN
    SET @tmp_sql = CONCAT('DROP INDEX `', p_indice, '` ON `', p_tabla, '`');
    PREPARE tmp_stmt FROM @tmp_sql;
    EXECUTE tmp_stmt;
    DEALLOCATE PREPARE tmp_stmt;
  END IF;
END//
DELIMITER ;

CALL tmp_eliminar_indice('seg_usuario',           'ix_seg_usuario_area');
CALL tmp_eliminar_indice('seg_usuario_rol',       'ix_seg_usuario_rol_rol');
CALL tmp_eliminar_indice('seg_usuario_rol',       'ix_seg_usuario_rol_asignado_por');
CALL tmp_eliminar_indice('cat_transicion_estado', 'ix_cat_transicion_origen');
CALL tmp_eliminar_indice('cat_transicion_estado', 'ix_cat_transicion_destino');
CALL tmp_eliminar_indice('cat_transicion_estado', 'ix_cat_transicion_rol');
CALL tmp_eliminar_indice('sol_solicitud',         'ix_sol_solicitud_area_estado');
CALL tmp_eliminar_indice('sol_solicitud',         'ix_sol_solicitud_responsable');
CALL tmp_eliminar_indice('sol_solicitud',         'ix_sol_solicitud_solicitante');
CALL tmp_eliminar_indice('sol_solicitud',         'ix_sol_solicitud_vencimiento');
CALL tmp_eliminar_indice('sol_solicitud',         'ix_sol_solicitud_estado_registro');
CALL tmp_eliminar_indice('sol_solicitud',         'ix_sol_solicitud_estado_atencion');
CALL tmp_eliminar_indice('sol_solicitud',         'ix_sol_solicitud_fecha_registro');
CALL tmp_eliminar_indice('sol_solicitud',         'ix_sol_solicitud_tipo');
CALL tmp_eliminar_indice('sol_solicitud',         'ix_sol_solicitud_prioridad');
CALL tmp_eliminar_indice('sol_historial',         'ix_sol_historial_solicitud');
CALL tmp_eliminar_indice('sol_historial',         'ix_sol_historial_transicion');
CALL tmp_eliminar_indice('sol_historial',         'ix_sol_historial_estado_nuevo');
CALL tmp_eliminar_indice('sol_historial',         'ix_sol_historial_estado_anterior');
CALL tmp_eliminar_indice('sol_historial',         'ix_sol_historial_usuario');
CALL tmp_eliminar_indice('sol_historial',         'ix_sol_historial_resp_anterior');
CALL tmp_eliminar_indice('sol_historial',         'ix_sol_historial_resp_nuevo');
CALL tmp_eliminar_indice('sol_historial',         'ix_sol_historial_prio_anterior');
CALL tmp_eliminar_indice('sol_historial',         'ix_sol_historial_prio_nueva');
CALL tmp_eliminar_indice('sol_comentario',        'ix_sol_comentario_solicitud');
CALL tmp_eliminar_indice('sol_comentario',        'ix_sol_comentario_usuario');
CALL tmp_eliminar_indice('sol_adjunto',           'ix_sol_adjunto_solicitud');
CALL tmp_eliminar_indice('sol_adjunto',           'ix_sol_adjunto_usuario');
CALL tmp_eliminar_indice('sol_adjunto',           'ix_sol_adjunto_tipo_archivo');
CALL tmp_eliminar_indice('not_notificacion',      'ix_not_notificacion_usuario');
CALL tmp_eliminar_indice('not_notificacion',      'ix_not_notificacion_solicitud');
CALL tmp_eliminar_indice('not_correo',            'ix_not_correo_cola');
CALL tmp_eliminar_indice('cfg_parametro',         'ix_cfg_parametro_actualizado_por');
CALL tmp_eliminar_indice('aud_auditoria',         'ix_aud_auditoria_entidad');
CALL tmp_eliminar_indice('aud_auditoria',         'ix_aud_auditoria_fecha');
CALL tmp_eliminar_indice('aud_auditoria',         'ix_aud_auditoria_usuario');

DROP PROCEDURE tmp_eliminar_indice;

DELETE FROM cfg_version_esquema WHERE version = 'V004';
