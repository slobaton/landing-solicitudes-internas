-- =============================================================================
-- V004__crear_indices.sql
-- Sistema de seguimiento de solicitudes internas
-- Ejecuta: sis_migrador
-- Fuente: 10_indices_rendimiento/indices_rendimiento.md §3 y §4
-- Cada indice indica el patron de acceso (PA) o la FK que soporta.
-- Se crean ANTES de las FK (V005) para que InnoDB no genere indices con
-- nombres automaticos.
-- Los indices UNIQUE ya se crearon con las tablas en V003.
-- =============================================================================

USE sis_solicitudes;

-- seg_usuario ------------------------------------------------------------------
CREATE INDEX ix_seg_usuario_area             ON seg_usuario (area_id, activo);            -- FK area; candidatos a responsable (RF-04)

-- seg_usuario_rol --------------------------------------------------------------
CREATE INDEX ix_seg_usuario_rol_rol          ON seg_usuario_rol (rol_id);                  -- FK rol; usuarios con rol X
CREATE INDEX ix_seg_usuario_rol_asignado_por ON seg_usuario_rol (asignado_por);            -- FK

-- cat_transicion_estado --------------------------------------------------------
CREATE INDEX ix_cat_transicion_origen        ON cat_transicion_estado (estado_origen_id);  -- FK (no depende de rol_clave)
CREATE INDEX ix_cat_transicion_destino       ON cat_transicion_estado (estado_destino_id); -- FK
CREATE INDEX ix_cat_transicion_rol           ON cat_transicion_estado (rol_id);            -- FK

-- sol_solicitud ----------------------------------------------------------------
CREATE INDEX ix_sol_solicitud_area_estado      ON sol_solicitud (area_destino_id, estado_id, fecha_registro);         -- PA-03, PA-07, FK area
CREATE INDEX ix_sol_solicitud_responsable      ON sol_solicitud (responsable_id, estado_id, fecha_limite);            -- PA-04, PA-07, FK responsable
CREATE INDEX ix_sol_solicitud_solicitante      ON sol_solicitud (solicitante_id, fecha_registro);                     -- PA-05, FK solicitante
CREATE INDEX ix_sol_solicitud_vencimiento      ON sol_solicitud (alerta_vencimiento_en, estado_id, fecha_limite);     -- PA-09
CREATE INDEX ix_sol_solicitud_estado_registro  ON sol_solicitud (estado_id, fecha_registro);                          -- PA-07, FK estado
CREATE INDEX ix_sol_solicitud_estado_atencion  ON sol_solicitud (estado_id, fecha_atencion);                          -- PA-10, PA-13
CREATE INDEX ix_sol_solicitud_fecha_registro   ON sol_solicitud (fecha_registro);                                     -- PA-07, PA-10
CREATE INDEX ix_sol_solicitud_tipo             ON sol_solicitud (tipo_solicitud_id);                                  -- FK tipo
CREATE INDEX ix_sol_solicitud_prioridad        ON sol_solicitud (prioridad_id, fecha_registro);                       -- PA-07, FK prioridad

-- sol_historial ----------------------------------------------------------------
CREATE INDEX ix_sol_historial_solicitud        ON sol_historial (solicitud_id, id);                                   -- PA-06, FK solicitud
CREATE INDEX ix_sol_historial_transicion       ON sol_historial (transicion_id, estado_anterior_id, estado_nuevo_id); -- FK compuesta (D-03)
CREATE INDEX ix_sol_historial_estado_nuevo     ON sol_historial (estado_nuevo_id, fecha);                             -- PA-10, FK
CREATE INDEX ix_sol_historial_estado_anterior  ON sol_historial (estado_anterior_id);                                 -- FK
CREATE INDEX ix_sol_historial_usuario          ON sol_historial (usuario_id, fecha);                                  -- Auditoria por usuario, FK
CREATE INDEX ix_sol_historial_resp_anterior    ON sol_historial (responsable_anterior_id);                            -- FK
CREATE INDEX ix_sol_historial_resp_nuevo       ON sol_historial (responsable_nuevo_id);                               -- FK
CREATE INDEX ix_sol_historial_prio_anterior    ON sol_historial (prioridad_anterior_id);                              -- FK
CREATE INDEX ix_sol_historial_prio_nueva       ON sol_historial (prioridad_nueva_id);                                 -- FK

-- sol_comentario ---------------------------------------------------------------
CREATE INDEX ix_sol_comentario_solicitud       ON sol_comentario (solicitud_id, id);                                  -- PA-06, FK
CREATE INDEX ix_sol_comentario_usuario         ON sol_comentario (usuario_id);                                        -- FK

-- sol_adjunto ------------------------------------------------------------------
CREATE INDEX ix_sol_adjunto_solicitud          ON sol_adjunto (solicitud_id, id);                                     -- PA-06, FK
CREATE INDEX ix_sol_adjunto_usuario            ON sol_adjunto (usuario_id);                                           -- FK
CREATE INDEX ix_sol_adjunto_tipo_archivo       ON sol_adjunto (tipo_archivo_id);                                      -- FK

-- not_notificacion -------------------------------------------------------------
CREATE INDEX ix_not_notificacion_usuario       ON not_notificacion (usuario_id, leida_en, creada_en);                 -- PA-11, FK
CREATE INDEX ix_not_notificacion_solicitud     ON not_notificacion (solicitud_id);                                    -- FK

-- not_correo -------------------------------------------------------------------
CREATE INDEX ix_not_correo_cola                ON not_correo (estado, proximo_intento_en);                            -- PA-12

-- cfg_parametro ----------------------------------------------------------------
CREATE INDEX ix_cfg_parametro_actualizado_por  ON cfg_parametro (actualizado_por);                                    -- FK

-- aud_auditoria ----------------------------------------------------------------
CREATE INDEX ix_aud_auditoria_entidad          ON aud_auditoria (entidad, registro_id, fecha);                        -- Historia de un registro
CREATE INDEX ix_aud_auditoria_fecha            ON aud_auditoria (fecha);                                              -- Consultas por periodo
CREATE INDEX ix_aud_auditoria_usuario          ON aud_auditoria (usuario_id, fecha);                                  -- Que hizo un usuario, FK

INSERT INTO cfg_version_esquema (version, descripcion, script, aplicado_por)
VALUES ('V004', 'Crear indices secundarios vinculados a patrones de acceso y FK',
        'V004__crear_indices.sql', CURRENT_USER());
