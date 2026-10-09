-- =============================================================================
-- V005__crear_claves_foraneas.sql
-- Sistema de seguimiento de solicitudes internas
-- Ejecuta: sis_migrador
-- Fuente: 06_integridad/integridad.md §3-§4; 03_modelo_logico/modelo_logico.md
-- Politica: ON DELETE RESTRICT ON UPDATE RESTRICT en todas (sin borrado fisico,
-- R-04, R-06; compatible con los CHECK de V003).
-- La FK simple sol_historial.transicion_id queda subsumida por la FK compuesta
-- fk_sol_historial_transicion (CI-03).
-- =============================================================================

USE sis_solicitudes;

-- Seguridad --------------------------------------------------------------------
ALTER TABLE seg_usuario
  ADD CONSTRAINT fk_seg_usuario_area
    FOREIGN KEY (area_id) REFERENCES org_area (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE seg_usuario_rol
  ADD CONSTRAINT fk_seg_usuario_rol_usuario
    FOREIGN KEY (usuario_id) REFERENCES seg_usuario (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_seg_usuario_rol_rol
    FOREIGN KEY (rol_id) REFERENCES seg_rol (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_seg_usuario_rol_asignado_por
    FOREIGN KEY (asignado_por) REFERENCES seg_usuario (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

-- Organizacion y catalogos -----------------------------------------------------
ALTER TABLE org_area
  ADD CONSTRAINT fk_org_area_jefe
    FOREIGN KEY (jefe_usuario_id) REFERENCES seg_usuario (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE cat_transicion_estado
  ADD CONSTRAINT fk_cat_transicion_origen
    FOREIGN KEY (estado_origen_id) REFERENCES cat_estado (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_cat_transicion_destino
    FOREIGN KEY (estado_destino_id) REFERENCES cat_estado (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_cat_transicion_rol
    FOREIGN KEY (rol_id) REFERENCES seg_rol (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

-- Solicitudes ------------------------------------------------------------------
ALTER TABLE sol_solicitud
  ADD CONSTRAINT fk_sol_solicitud_solicitante
    FOREIGN KEY (solicitante_id) REFERENCES seg_usuario (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sol_solicitud_area_destino
    FOREIGN KEY (area_destino_id) REFERENCES org_area (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sol_solicitud_tipo
    FOREIGN KEY (tipo_solicitud_id) REFERENCES cat_tipo_solicitud (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sol_solicitud_prioridad
    FOREIGN KEY (prioridad_id) REFERENCES cat_prioridad (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sol_solicitud_estado
    FOREIGN KEY (estado_id) REFERENCES cat_estado (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sol_solicitud_responsable
    FOREIGN KEY (responsable_id) REFERENCES seg_usuario (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE sol_historial
  ADD CONSTRAINT fk_sol_historial_solicitud
    FOREIGN KEY (solicitud_id) REFERENCES sol_solicitud (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sol_historial_usuario
    FOREIGN KEY (usuario_id) REFERENCES seg_usuario (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  -- FK compuesta (D-03): los estados guardados deben coincidir con la transicion.
  ADD CONSTRAINT fk_sol_historial_transicion
    FOREIGN KEY (transicion_id, estado_anterior_id, estado_nuevo_id)
    REFERENCES cat_transicion_estado (id, estado_origen_id, estado_destino_id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sol_historial_estado_anterior
    FOREIGN KEY (estado_anterior_id) REFERENCES cat_estado (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sol_historial_estado_nuevo
    FOREIGN KEY (estado_nuevo_id) REFERENCES cat_estado (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sol_historial_resp_anterior
    FOREIGN KEY (responsable_anterior_id) REFERENCES seg_usuario (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sol_historial_resp_nuevo
    FOREIGN KEY (responsable_nuevo_id) REFERENCES seg_usuario (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sol_historial_prio_anterior
    FOREIGN KEY (prioridad_anterior_id) REFERENCES cat_prioridad (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sol_historial_prio_nueva
    FOREIGN KEY (prioridad_nueva_id) REFERENCES cat_prioridad (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE sol_comentario
  ADD CONSTRAINT fk_sol_comentario_solicitud
    FOREIGN KEY (solicitud_id) REFERENCES sol_solicitud (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sol_comentario_usuario
    FOREIGN KEY (usuario_id) REFERENCES seg_usuario (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE sol_adjunto
  ADD CONSTRAINT fk_sol_adjunto_solicitud
    FOREIGN KEY (solicitud_id) REFERENCES sol_solicitud (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sol_adjunto_usuario
    FOREIGN KEY (usuario_id) REFERENCES seg_usuario (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sol_adjunto_tipo_archivo
    FOREIGN KEY (tipo_archivo_id) REFERENCES cfg_tipo_archivo (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

-- Notificaciones ---------------------------------------------------------------
ALTER TABLE not_notificacion
  ADD CONSTRAINT fk_not_notificacion_usuario
    FOREIGN KEY (usuario_id) REFERENCES seg_usuario (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_not_notificacion_solicitud
    FOREIGN KEY (solicitud_id) REFERENCES sol_solicitud (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE not_correo
  ADD CONSTRAINT fk_not_correo_notificacion
    FOREIGN KEY (notificacion_id) REFERENCES not_notificacion (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

-- Configuracion y auditoria ----------------------------------------------------
ALTER TABLE cfg_parametro
  ADD CONSTRAINT fk_cfg_parametro_actualizado_por
    FOREIGN KEY (actualizado_por) REFERENCES seg_usuario (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE aud_auditoria
  ADD CONSTRAINT fk_aud_auditoria_usuario
    FOREIGN KEY (usuario_id) REFERENCES seg_usuario (id)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

INSERT INTO cfg_version_esquema (version, descripcion, script, aplicado_por)
VALUES ('V005', 'Crear claves foraneas (RESTRICT)',
        'V005__crear_claves_foraneas.sql', CURRENT_USER());
