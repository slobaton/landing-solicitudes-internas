-- =============================================================================
-- V006__crear_triggers_integridad.sql
-- Sistema de seguimiento de solicitudes internas
-- Ejecuta: sis_migrador (queda como DEFINER de los triggers)
--   Con binlog activo puede requerir log_bin_trust_function_creators=1 o
--   privilegios elevados: ver 12_migraciones/plan_migraciones.md §2.
-- Fuente: 06_integridad/integridad.md §6 (TRG-01 .. TRG-10)
-- Todos los mensajes empiezan por el codigo de la regla para que la
-- aplicacion los traduzca (transacciones_concurrencia.md §8).
-- Comparaciones de texto con COLLATE utf8mb4_bin para detectar tambien
-- cambios solo de mayusculas o tildes.
-- =============================================================================

USE sis_solicitudes;

DELIMITER //

-- -----------------------------------------------------------------------------
-- TRG-01: estado inicial y catalogos activos (RN-D01, RN-D12)
-- -----------------------------------------------------------------------------
CREATE TRIGGER trg_sol_solicitud_bi
BEFORE INSERT ON sol_solicitud
FOR EACH ROW
BEGIN
  IF NEW.estado_id <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D01: toda solicitud nace en estado REGISTRADA';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM seg_usuario WHERE id = NEW.solicitante_id AND activo = 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D12: el solicitante no existe o esta inactivo';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM org_area WHERE id = NEW.area_destino_id AND activo = 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D12: el area destino no existe o esta inactiva';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM cat_tipo_solicitud WHERE id = NEW.tipo_solicitud_id AND activo = 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D12: el tipo de solicitud no existe o esta inactivo';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM cat_prioridad WHERE id = NEW.prioridad_id AND activo = 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D12: la prioridad no existe o esta inactiva';
  END IF;
END//

-- -----------------------------------------------------------------------------
-- TRG-02: reglas de actualizacion de la solicitud
--   (a) inmutables  (b) estado final  (c) transicion activa
--   (d) edicion solo en REGISTRADA  (e) responsable del area  (f) catalogos activos
-- -----------------------------------------------------------------------------
CREATE TRIGGER trg_sol_solicitud_bu
BEFORE UPDATE ON sol_solicitud
FOR EACH ROW
BEGIN
  DECLARE v_final TINYINT UNSIGNED;

  -- (a) S-04, RF-03
  IF NOT (NEW.numero <=> OLD.numero)
     OR NOT (NEW.solicitante_id <=> OLD.solicitante_id)
     OR NOT (NEW.area_destino_id <=> OLD.area_destino_id)
     OR NOT (NEW.fecha_registro <=> OLD.fecha_registro)
     OR NOT (NEW.creado_en <=> OLD.creado_en) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'INT-01: numero, solicitante, area destino y fecha de registro son inmutables';
  END IF;

  -- (b) RN-D11
  SET v_final = (SELECT es_final FROM cat_estado WHERE id = OLD.estado_id);
  IF v_final = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D11: la solicitud esta en un estado final y no admite cambios';
  END IF;

  -- (c) RC-03
  IF NEW.estado_id <> OLD.estado_id
     AND NOT EXISTS (SELECT 1
                       FROM cat_transicion_estado
                      WHERE estado_origen_id = OLD.estado_id
                        AND estado_destino_id = NEW.estado_id
                        AND activa = 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RC-03: transicion de estado no permitida';
  END IF;

  -- (d) RN-D10
  IF OLD.estado_id <> 1
     AND (NOT (NEW.titulo COLLATE utf8mb4_bin <=> OLD.titulo COLLATE utf8mb4_bin)
          OR NOT (NEW.descripcion COLLATE utf8mb4_bin <=> OLD.descripcion COLLATE utf8mb4_bin)
          OR NOT (NEW.tipo_solicitud_id <=> OLD.tipo_solicitud_id)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D10: solo se puede editar una solicitud en estado REGISTRADA';
  END IF;

  -- (e) RN-D03
  IF NEW.responsable_id IS NOT NULL
     AND NOT (NEW.responsable_id <=> OLD.responsable_id)
     AND NOT EXISTS (SELECT 1
                       FROM seg_usuario
                      WHERE id = NEW.responsable_id
                        AND area_id = NEW.area_destino_id
                        AND activo = 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D03: el responsable debe estar activo y pertenecer al area destino';
  END IF;

  -- (f) RN-D12
  IF NOT (NEW.prioridad_id <=> OLD.prioridad_id)
     AND NOT EXISTS (SELECT 1 FROM cat_prioridad WHERE id = NEW.prioridad_id AND activo = 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D12: la prioridad no existe o esta inactiva';
  END IF;
  IF NOT (NEW.tipo_solicitud_id <=> OLD.tipo_solicitud_id)
     AND NOT EXISTS (SELECT 1 FROM cat_tipo_solicitud WHERE id = NEW.tipo_solicitud_id AND activo = 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D12: el tipo de solicitud no existe o esta inactivo';
  END IF;
END//

-- -----------------------------------------------------------------------------
-- TRG-03: transicion activa y motivo obligatorio (RC-03, RC-05, RN-D04, RN-D05)
-- -----------------------------------------------------------------------------
CREATE TRIGGER trg_sol_historial_bi
BEFORE INSERT ON sol_historial
FOR EACH ROW
BEGIN
  DECLARE v_activa  TINYINT UNSIGNED;
  DECLARE v_motivo  TINYINT UNSIGNED;

  IF NEW.transicion_id IS NOT NULL THEN
    SET v_activa = (SELECT activa          FROM cat_transicion_estado WHERE id = NEW.transicion_id);
    SET v_motivo = (SELECT requiere_motivo FROM cat_transicion_estado WHERE id = NEW.transicion_id);
    IF v_activa IS NULL OR v_activa = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RC-03: la transicion no existe o esta inactiva';
    END IF;
    IF v_motivo = 1 AND (NEW.motivo IS NULL OR CHAR_LENGTH(TRIM(NEW.motivo)) = 0) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RC-05: la transicion exige un motivo';
    END IF;
  END IF;
END//

-- -----------------------------------------------------------------------------
-- TRG-04: historial inmutable (RN-D13, RNF-05)
-- -----------------------------------------------------------------------------
CREATE TRIGGER trg_sol_historial_bu
BEFORE UPDATE ON sol_historial
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D13: el historial de la solicitud no se puede modificar';
END//

CREATE TRIGGER trg_sol_historial_bd
BEFORE DELETE ON sol_historial
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D13: el historial de la solicitud no se puede eliminar';
END//

-- -----------------------------------------------------------------------------
-- TRG-05: comentarios y adjuntos inmutables (RF-09, S-08)
-- -----------------------------------------------------------------------------
CREATE TRIGGER trg_sol_comentario_bu
BEFORE UPDATE ON sol_comentario
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D13: los comentarios no se pueden modificar';
END//

CREATE TRIGGER trg_sol_comentario_bd
BEFORE DELETE ON sol_comentario
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D13: los comentarios no se pueden eliminar';
END//

CREATE TRIGGER trg_sol_adjunto_bu
BEFORE UPDATE ON sol_adjunto
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'S-08: los adjuntos no se pueden modificar';
END//

CREATE TRIGGER trg_sol_adjunto_bd
BEFORE DELETE ON sol_adjunto
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'S-08: los adjuntos no se pueden eliminar';
END//

-- -----------------------------------------------------------------------------
-- TRG-06: auditoria inmutable (R-05)
-- -----------------------------------------------------------------------------
CREATE TRIGGER trg_aud_auditoria_bu
BEFORE UPDATE ON aud_auditoria
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R-05: la auditoria no se puede modificar';
END//

CREATE TRIGGER trg_aud_auditoria_bd
BEFORE DELETE ON aud_auditoria
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R-05: la auditoria no se puede eliminar';
END//

-- -----------------------------------------------------------------------------
-- TRG-07: tipo de archivo activo y tamano maximo (RN-D14, S-07)
-- -----------------------------------------------------------------------------
CREATE TRIGGER trg_sol_adjunto_bi
BEFORE INSERT ON sol_adjunto
FOR EACH ROW
BEGIN
  DECLARE v_max_mb BIGINT UNSIGNED;

  IF NOT EXISTS (SELECT 1 FROM cfg_tipo_archivo WHERE id = NEW.tipo_archivo_id AND activo = 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D14: tipo de archivo no permitido';
  END IF;

  SET v_max_mb = (SELECT CAST(valor AS UNSIGNED)
                    FROM cfg_parametro
                   WHERE clave = 'ADJUNTO_TAMANO_MAX_MB');
  IF v_max_mb IS NOT NULL AND NEW.tamano_bytes > v_max_mb * 1048576 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-D14: el archivo supera el tamano maximo permitido';
  END IF;
END//

-- -----------------------------------------------------------------------------
-- TRG-08: coherencia del jefe de area (RD-03, S-01, S-02)
-- -----------------------------------------------------------------------------
CREATE TRIGGER trg_org_area_bi
BEFORE INSERT ON org_area
FOR EACH ROW
BEGIN
  -- El jefe debe pertenecer al area; un area nueva aun no tiene miembros.
  IF NEW.jefe_usuario_id IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'S-02: un area nueva se crea sin jefe; asignelo despues';
  END IF;
END//

CREATE TRIGGER trg_org_area_bu
BEFORE UPDATE ON org_area
FOR EACH ROW
BEGIN
  IF NEW.jefe_usuario_id IS NOT NULL
     AND NOT (NEW.jefe_usuario_id <=> OLD.jefe_usuario_id)
     AND NOT EXISTS (SELECT 1
                       FROM seg_usuario u
                       JOIN seg_usuario_rol ur ON ur.usuario_id = u.id AND ur.rol_id = 2
                      WHERE u.id = NEW.jefe_usuario_id
                        AND u.area_id = NEW.id
                        AND u.activo = 1) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'S-02: el jefe debe estar activo, pertenecer al area y tener rol JEFE_AREA';
  END IF;
END//

-- -----------------------------------------------------------------------------
-- TRG-09: un jefe no cambia de area ni se inactiva sin liberar la jefatura
-- -----------------------------------------------------------------------------
CREATE TRIGGER trg_seg_usuario_bu
BEFORE UPDATE ON seg_usuario
FOR EACH ROW
BEGIN
  IF (NOT (NEW.area_id <=> OLD.area_id) OR (OLD.activo = 1 AND NEW.activo = 0))
     AND EXISTS (SELECT 1 FROM org_area WHERE jefe_usuario_id = OLD.id) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'S-02: el usuario es jefe de un area; libere la jefatura antes de cambiarlo';
  END IF;
END//

-- -----------------------------------------------------------------------------
-- TRG-10: no quitar el rol JEFE_AREA a quien figura como jefe
-- -----------------------------------------------------------------------------
CREATE TRIGGER trg_seg_usuario_rol_bd
BEFORE DELETE ON seg_usuario_rol
FOR EACH ROW
BEGIN
  IF OLD.rol_id = 2 AND EXISTS (SELECT 1 FROM org_area WHERE jefe_usuario_id = OLD.usuario_id) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'S-02: el usuario es jefe de un area; libere la jefatura antes de quitar el rol';
  END IF;
END//

DELIMITER ;

INSERT INTO cfg_version_esquema (version, descripcion, script, aplicado_por)
VALUES ('V006', 'Crear triggers de integridad TRG-01..TRG-10',
        'V006__crear_triggers_integridad.sql', CURRENT_USER());
