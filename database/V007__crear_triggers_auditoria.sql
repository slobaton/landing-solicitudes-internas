-- =============================================================================
-- V007__crear_triggers_auditoria.sql
-- Sistema de seguimiento de solicitudes internas
-- Ejecuta: sis_migrador (queda como DEFINER de los triggers)
--   Con binlog activo puede requerir log_bin_trust_function_creators=1 o
--   privilegios elevados: ver 12_migraciones/plan_migraciones.md §2.
-- Fuente: 08_auditoria/auditoria.md §2-§3 (RNF-05, R-05)
--
-- Contexto de sesion que la aplicacion fija en cada peticion:
--   @sis_usuario_id  id del usuario autenticado (NULL = sistema / carga / DBA)
--   @sis_ip          IP del cliente
-- Reglas:
--   * UPDATE sin cambios en columnas de negocio no genera registro.
--   * En seg_usuario se ignoran ultimo_acceso_en y actualizado_en (el login
--     se audita desde la aplicacion como LOGIN).
--   * password_hash NUNCA se copia; solo "password_cambiado": true/false.
--   * Comparaciones de texto con intercalacion binaria para detectar cambios
--     de mayusculas o tildes.
-- =============================================================================

USE sis_solicitudes;

DELIMITER //

-- =============================================================================
-- seg_usuario
-- =============================================================================
CREATE TRIGGER trg_seg_usuario_aud_ai
AFTER INSERT ON seg_usuario
FOR EACH ROW
BEGIN
  INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                             valores_anteriores, valores_nuevos, ip_origen)
  VALUES ('seg_usuario', CAST(NEW.id AS CHAR), 'CREAR', CAST(@sis_usuario_id AS UNSIGNED),
          NULL,
          JSON_OBJECT('nombre_usuario', NEW.nombre_usuario, 'nombres', NEW.nombres,
                      'apellidos', NEW.apellidos, 'correo', NEW.correo,
                      'area_id', NEW.area_id, 'activo', NEW.activo),
          LEFT(@sis_ip, 45));
END//

CREATE TRIGGER trg_seg_usuario_aud_au
AFTER UPDATE ON seg_usuario
FOR EACH ROW
BEGIN
  IF NOT (    OLD.nombre_usuario COLLATE utf8mb4_bin <=> NEW.nombre_usuario COLLATE utf8mb4_bin
          AND OLD.nombres        COLLATE utf8mb4_bin <=> NEW.nombres        COLLATE utf8mb4_bin
          AND OLD.apellidos      COLLATE utf8mb4_bin <=> NEW.apellidos      COLLATE utf8mb4_bin
          AND OLD.correo         COLLATE utf8mb4_bin <=> NEW.correo         COLLATE utf8mb4_bin
          AND OLD.password_hash  <=> NEW.password_hash
          AND OLD.area_id        <=> NEW.area_id
          AND OLD.activo         <=> NEW.activo) THEN
    INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                               valores_anteriores, valores_nuevos, ip_origen)
    VALUES ('seg_usuario', CAST(NEW.id AS CHAR),
            CASE WHEN OLD.activo = 1 AND NEW.activo = 0 THEN 'INACTIVAR'
                 WHEN OLD.activo = 0 AND NEW.activo = 1 THEN 'ACTIVAR'
                 ELSE 'MODIFICAR' END,
            CAST(@sis_usuario_id AS UNSIGNED),
            JSON_OBJECT('nombre_usuario', OLD.nombre_usuario, 'nombres', OLD.nombres,
                        'apellidos', OLD.apellidos, 'correo', OLD.correo,
                        'area_id', OLD.area_id, 'activo', OLD.activo),
            JSON_OBJECT('nombre_usuario', NEW.nombre_usuario, 'nombres', NEW.nombres,
                        'apellidos', NEW.apellidos, 'correo', NEW.correo,
                        'area_id', NEW.area_id, 'activo', NEW.activo,
                        'password_cambiado',
                        JSON_EXTRACT(IF(OLD.password_hash <=> NEW.password_hash, 'false', 'true'), '$')),
            LEFT(@sis_ip, 45));
  END IF;
END//

-- =============================================================================
-- seg_usuario_rol
-- =============================================================================
CREATE TRIGGER trg_seg_usuario_rol_aud_ai
AFTER INSERT ON seg_usuario_rol
FOR EACH ROW
BEGIN
  INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                             valores_anteriores, valores_nuevos, ip_origen)
  VALUES ('seg_usuario_rol', CONCAT(NEW.usuario_id, ':', NEW.rol_id), 'ASIGNAR_ROL',
          CAST(@sis_usuario_id AS UNSIGNED),
          NULL,
          JSON_OBJECT('usuario_id', NEW.usuario_id, 'rol_id', NEW.rol_id,
                      'asignado_por', NEW.asignado_por),
          LEFT(@sis_ip, 45));
END//

CREATE TRIGGER trg_seg_usuario_rol_aud_ad
AFTER DELETE ON seg_usuario_rol
FOR EACH ROW
BEGIN
  INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                             valores_anteriores, valores_nuevos, ip_origen)
  VALUES ('seg_usuario_rol', CONCAT(OLD.usuario_id, ':', OLD.rol_id), 'QUITAR_ROL',
          CAST(@sis_usuario_id AS UNSIGNED),
          JSON_OBJECT('usuario_id', OLD.usuario_id, 'rol_id', OLD.rol_id,
                      'asignado_en', OLD.asignado_en, 'asignado_por', OLD.asignado_por),
          NULL,
          LEFT(@sis_ip, 45));
END//

-- =============================================================================
-- org_area
-- =============================================================================
CREATE TRIGGER trg_org_area_aud_ai
AFTER INSERT ON org_area
FOR EACH ROW
BEGIN
  INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                             valores_anteriores, valores_nuevos, ip_origen)
  VALUES ('org_area', CAST(NEW.id AS CHAR), 'CREAR', CAST(@sis_usuario_id AS UNSIGNED),
          NULL,
          JSON_OBJECT('codigo', NEW.codigo, 'nombre', NEW.nombre,
                      'jefe_usuario_id', NEW.jefe_usuario_id, 'activo', NEW.activo),
          LEFT(@sis_ip, 45));
END//

CREATE TRIGGER trg_org_area_aud_au
AFTER UPDATE ON org_area
FOR EACH ROW
BEGIN
  IF NOT (    OLD.codigo COLLATE ascii_bin   <=> NEW.codigo COLLATE ascii_bin
          AND OLD.nombre COLLATE utf8mb4_bin <=> NEW.nombre COLLATE utf8mb4_bin
          AND OLD.jefe_usuario_id <=> NEW.jefe_usuario_id
          AND OLD.activo          <=> NEW.activo) THEN
    INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                               valores_anteriores, valores_nuevos, ip_origen)
    VALUES ('org_area', CAST(NEW.id AS CHAR),
            CASE WHEN OLD.activo = 1 AND NEW.activo = 0 THEN 'INACTIVAR'
                 WHEN OLD.activo = 0 AND NEW.activo = 1 THEN 'ACTIVAR'
                 ELSE 'MODIFICAR' END,
            CAST(@sis_usuario_id AS UNSIGNED),
            JSON_OBJECT('codigo', OLD.codigo, 'nombre', OLD.nombre,
                        'jefe_usuario_id', OLD.jefe_usuario_id, 'activo', OLD.activo),
            JSON_OBJECT('codigo', NEW.codigo, 'nombre', NEW.nombre,
                        'jefe_usuario_id', NEW.jefe_usuario_id, 'activo', NEW.activo),
            LEFT(@sis_ip, 45));
  END IF;
END//

-- =============================================================================
-- cat_tipo_solicitud
-- =============================================================================
CREATE TRIGGER trg_cat_tipo_solicitud_aud_ai
AFTER INSERT ON cat_tipo_solicitud
FOR EACH ROW
BEGIN
  INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                             valores_anteriores, valores_nuevos, ip_origen)
  VALUES ('cat_tipo_solicitud', CAST(NEW.id AS CHAR), 'CREAR', CAST(@sis_usuario_id AS UNSIGNED),
          NULL,
          JSON_OBJECT('nombre', NEW.nombre, 'descripcion', NEW.descripcion, 'activo', NEW.activo),
          LEFT(@sis_ip, 45));
END//

CREATE TRIGGER trg_cat_tipo_solicitud_aud_au
AFTER UPDATE ON cat_tipo_solicitud
FOR EACH ROW
BEGIN
  IF NOT (    OLD.nombre      COLLATE utf8mb4_bin <=> NEW.nombre      COLLATE utf8mb4_bin
          AND OLD.descripcion COLLATE utf8mb4_bin <=> NEW.descripcion COLLATE utf8mb4_bin
          AND OLD.activo <=> NEW.activo) THEN
    INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                               valores_anteriores, valores_nuevos, ip_origen)
    VALUES ('cat_tipo_solicitud', CAST(NEW.id AS CHAR),
            CASE WHEN OLD.activo = 1 AND NEW.activo = 0 THEN 'INACTIVAR'
                 WHEN OLD.activo = 0 AND NEW.activo = 1 THEN 'ACTIVAR'
                 ELSE 'MODIFICAR' END,
            CAST(@sis_usuario_id AS UNSIGNED),
            JSON_OBJECT('nombre', OLD.nombre, 'descripcion', OLD.descripcion, 'activo', OLD.activo),
            JSON_OBJECT('nombre', NEW.nombre, 'descripcion', NEW.descripcion, 'activo', NEW.activo),
            LEFT(@sis_ip, 45));
  END IF;
END//

-- =============================================================================
-- cat_prioridad
-- =============================================================================
CREATE TRIGGER trg_cat_prioridad_aud_ai
AFTER INSERT ON cat_prioridad
FOR EACH ROW
BEGIN
  INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                             valores_anteriores, valores_nuevos, ip_origen)
  VALUES ('cat_prioridad', CAST(NEW.id AS CHAR), 'CREAR', CAST(@sis_usuario_id AS UNSIGNED),
          NULL,
          JSON_OBJECT('codigo', NEW.codigo, 'nombre', NEW.nombre, 'orden', NEW.orden,
                      'horas_max_atencion', NEW.horas_max_atencion, 'activo', NEW.activo),
          LEFT(@sis_ip, 45));
END//

CREATE TRIGGER trg_cat_prioridad_aud_au
AFTER UPDATE ON cat_prioridad
FOR EACH ROW
BEGIN
  IF NOT (    OLD.codigo <=> NEW.codigo
          AND OLD.nombre COLLATE utf8mb4_bin <=> NEW.nombre COLLATE utf8mb4_bin
          AND OLD.orden  <=> NEW.orden
          AND OLD.horas_max_atencion <=> NEW.horas_max_atencion
          AND OLD.activo <=> NEW.activo) THEN
    INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                               valores_anteriores, valores_nuevos, ip_origen)
    VALUES ('cat_prioridad', CAST(NEW.id AS CHAR),
            CASE WHEN OLD.activo = 1 AND NEW.activo = 0 THEN 'INACTIVAR'
                 WHEN OLD.activo = 0 AND NEW.activo = 1 THEN 'ACTIVAR'
                 ELSE 'MODIFICAR' END,
            CAST(@sis_usuario_id AS UNSIGNED),
            JSON_OBJECT('codigo', OLD.codigo, 'nombre', OLD.nombre, 'orden', OLD.orden,
                        'horas_max_atencion', OLD.horas_max_atencion, 'activo', OLD.activo),
            JSON_OBJECT('codigo', NEW.codigo, 'nombre', NEW.nombre, 'orden', NEW.orden,
                        'horas_max_atencion', NEW.horas_max_atencion, 'activo', NEW.activo),
            LEFT(@sis_ip, 45));
  END IF;
END//

-- =============================================================================
-- cat_transicion_estado (solo cambia por migracion, p. ej. activar T10)
-- =============================================================================
CREATE TRIGGER trg_cat_transicion_estado_aud_au
AFTER UPDATE ON cat_transicion_estado
FOR EACH ROW
BEGIN
  IF NOT (    OLD.estado_origen_id  <=> NEW.estado_origen_id
          AND OLD.estado_destino_id <=> NEW.estado_destino_id
          AND OLD.rol_id            <=> NEW.rol_id
          AND OLD.requiere_motivo   <=> NEW.requiere_motivo
          AND OLD.es_automatica     <=> NEW.es_automatica
          AND OLD.activa            <=> NEW.activa) THEN
    INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                               valores_anteriores, valores_nuevos, ip_origen)
    VALUES ('cat_transicion_estado', CAST(NEW.id AS CHAR),
            CASE WHEN OLD.activa = 1 AND NEW.activa = 0 THEN 'INACTIVAR'
                 WHEN OLD.activa = 0 AND NEW.activa = 1 THEN 'ACTIVAR'
                 ELSE 'MODIFICAR' END,
            CAST(@sis_usuario_id AS UNSIGNED),
            JSON_OBJECT('estado_origen_id', OLD.estado_origen_id, 'estado_destino_id', OLD.estado_destino_id,
                        'rol_id', OLD.rol_id, 'requiere_motivo', OLD.requiere_motivo,
                        'es_automatica', OLD.es_automatica, 'activa', OLD.activa),
            JSON_OBJECT('estado_origen_id', NEW.estado_origen_id, 'estado_destino_id', NEW.estado_destino_id,
                        'rol_id', NEW.rol_id, 'requiere_motivo', NEW.requiere_motivo,
                        'es_automatica', NEW.es_automatica, 'activa', NEW.activa),
            LEFT(@sis_ip, 45));
  END IF;
END//

-- =============================================================================
-- cfg_tipo_archivo
-- =============================================================================
CREATE TRIGGER trg_cfg_tipo_archivo_aud_ai
AFTER INSERT ON cfg_tipo_archivo
FOR EACH ROW
BEGIN
  INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                             valores_anteriores, valores_nuevos, ip_origen)
  VALUES ('cfg_tipo_archivo', CAST(NEW.id AS CHAR), 'CREAR', CAST(@sis_usuario_id AS UNSIGNED),
          NULL,
          JSON_OBJECT('extension', NEW.extension, 'mime_type', NEW.mime_type, 'activo', NEW.activo),
          LEFT(@sis_ip, 45));
END//

CREATE TRIGGER trg_cfg_tipo_archivo_aud_au
AFTER UPDATE ON cfg_tipo_archivo
FOR EACH ROW
BEGIN
  IF NOT (    OLD.extension <=> NEW.extension
          AND OLD.mime_type COLLATE ascii_bin <=> NEW.mime_type COLLATE ascii_bin
          AND OLD.activo    <=> NEW.activo) THEN
    INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                               valores_anteriores, valores_nuevos, ip_origen)
    VALUES ('cfg_tipo_archivo', CAST(NEW.id AS CHAR),
            CASE WHEN OLD.activo = 1 AND NEW.activo = 0 THEN 'INACTIVAR'
                 WHEN OLD.activo = 0 AND NEW.activo = 1 THEN 'ACTIVAR'
                 ELSE 'MODIFICAR' END,
            CAST(@sis_usuario_id AS UNSIGNED),
            JSON_OBJECT('extension', OLD.extension, 'mime_type', OLD.mime_type, 'activo', OLD.activo),
            JSON_OBJECT('extension', NEW.extension, 'mime_type', NEW.mime_type, 'activo', NEW.activo),
            LEFT(@sis_ip, 45));
  END IF;
END//

-- =============================================================================
-- cfg_parametro
-- =============================================================================
CREATE TRIGGER trg_cfg_parametro_aud_ai
AFTER INSERT ON cfg_parametro
FOR EACH ROW
BEGIN
  INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                             valores_anteriores, valores_nuevos, ip_origen)
  VALUES ('cfg_parametro', NEW.clave, 'CREAR', CAST(@sis_usuario_id AS UNSIGNED),
          NULL,
          JSON_OBJECT('valor', NEW.valor, 'tipo_dato', NEW.tipo_dato, 'descripcion', NEW.descripcion),
          LEFT(@sis_ip, 45));
END//

CREATE TRIGGER trg_cfg_parametro_aud_au
AFTER UPDATE ON cfg_parametro
FOR EACH ROW
BEGIN
  IF NOT (    OLD.clave <=> NEW.clave
          AND OLD.valor       COLLATE utf8mb4_bin <=> NEW.valor       COLLATE utf8mb4_bin
          AND OLD.tipo_dato   <=> NEW.tipo_dato
          AND OLD.descripcion COLLATE utf8mb4_bin <=> NEW.descripcion COLLATE utf8mb4_bin) THEN
    INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                               valores_anteriores, valores_nuevos, ip_origen)
    VALUES ('cfg_parametro', NEW.clave, 'MODIFICAR', CAST(@sis_usuario_id AS UNSIGNED),
            JSON_OBJECT('clave', OLD.clave, 'valor', OLD.valor, 'tipo_dato', OLD.tipo_dato,
                        'descripcion', OLD.descripcion),
            JSON_OBJECT('clave', NEW.clave, 'valor', NEW.valor, 'tipo_dato', NEW.tipo_dato,
                        'descripcion', NEW.descripcion, 'actualizado_por', NEW.actualizado_por),
            LEFT(@sis_ip, 45));
  END IF;
END//

-- =============================================================================
-- cfg_horario_laboral
-- =============================================================================
CREATE TRIGGER trg_cfg_horario_laboral_aud_ai
AFTER INSERT ON cfg_horario_laboral
FOR EACH ROW
BEGIN
  INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                             valores_anteriores, valores_nuevos, ip_origen)
  VALUES ('cfg_horario_laboral', CAST(NEW.dia_semana AS CHAR), 'CREAR', CAST(@sis_usuario_id AS UNSIGNED),
          NULL,
          JSON_OBJECT('hora_inicio', TIME_FORMAT(NEW.hora_inicio, '%H:%i'),
                      'hora_fin', TIME_FORMAT(NEW.hora_fin, '%H:%i'), 'activo', NEW.activo),
          LEFT(@sis_ip, 45));
END//

CREATE TRIGGER trg_cfg_horario_laboral_aud_au
AFTER UPDATE ON cfg_horario_laboral
FOR EACH ROW
BEGIN
  IF NOT (    OLD.dia_semana  <=> NEW.dia_semana
          AND OLD.hora_inicio <=> NEW.hora_inicio
          AND OLD.hora_fin    <=> NEW.hora_fin
          AND OLD.activo      <=> NEW.activo) THEN
    INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                               valores_anteriores, valores_nuevos, ip_origen)
    VALUES ('cfg_horario_laboral', CAST(NEW.dia_semana AS CHAR),
            CASE WHEN OLD.activo = 1 AND NEW.activo = 0 THEN 'INACTIVAR'
                 WHEN OLD.activo = 0 AND NEW.activo = 1 THEN 'ACTIVAR'
                 ELSE 'MODIFICAR' END,
            CAST(@sis_usuario_id AS UNSIGNED),
            JSON_OBJECT('hora_inicio', TIME_FORMAT(OLD.hora_inicio, '%H:%i'),
                        'hora_fin', TIME_FORMAT(OLD.hora_fin, '%H:%i'), 'activo', OLD.activo),
            JSON_OBJECT('hora_inicio', TIME_FORMAT(NEW.hora_inicio, '%H:%i'),
                        'hora_fin', TIME_FORMAT(NEW.hora_fin, '%H:%i'), 'activo', NEW.activo),
            LEFT(@sis_ip, 45));
  END IF;
END//

-- =============================================================================
-- cfg_feriado (unico borrado administrativo permitido: accion ELIMINAR)
-- =============================================================================
CREATE TRIGGER trg_cfg_feriado_aud_ai
AFTER INSERT ON cfg_feriado
FOR EACH ROW
BEGIN
  INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                             valores_anteriores, valores_nuevos, ip_origen)
  VALUES ('cfg_feriado', DATE_FORMAT(NEW.fecha, '%Y-%m-%d'), 'CREAR', CAST(@sis_usuario_id AS UNSIGNED),
          NULL,
          JSON_OBJECT('fecha', DATE_FORMAT(NEW.fecha, '%Y-%m-%d'), 'descripcion', NEW.descripcion),
          LEFT(@sis_ip, 45));
END//

CREATE TRIGGER trg_cfg_feriado_aud_au
AFTER UPDATE ON cfg_feriado
FOR EACH ROW
BEGIN
  IF NOT (    OLD.fecha <=> NEW.fecha
          AND OLD.descripcion COLLATE utf8mb4_bin <=> NEW.descripcion COLLATE utf8mb4_bin) THEN
    INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                               valores_anteriores, valores_nuevos, ip_origen)
    VALUES ('cfg_feriado', DATE_FORMAT(NEW.fecha, '%Y-%m-%d'), 'MODIFICAR', CAST(@sis_usuario_id AS UNSIGNED),
            JSON_OBJECT('fecha', DATE_FORMAT(OLD.fecha, '%Y-%m-%d'), 'descripcion', OLD.descripcion),
            JSON_OBJECT('fecha', DATE_FORMAT(NEW.fecha, '%Y-%m-%d'), 'descripcion', NEW.descripcion),
            LEFT(@sis_ip, 45));
  END IF;
END//

CREATE TRIGGER trg_cfg_feriado_aud_ad
AFTER DELETE ON cfg_feriado
FOR EACH ROW
BEGIN
  INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id,
                             valores_anteriores, valores_nuevos, ip_origen)
  VALUES ('cfg_feriado', DATE_FORMAT(OLD.fecha, '%Y-%m-%d'), 'ELIMINAR', CAST(@sis_usuario_id AS UNSIGNED),
          JSON_OBJECT('fecha', DATE_FORMAT(OLD.fecha, '%Y-%m-%d'), 'descripcion', OLD.descripcion),
          NULL,
          LEFT(@sis_ip, 45));
END//

DELIMITER ;

INSERT INTO cfg_version_esquema (version, descripcion, script, aplicado_por)
VALUES ('V007', 'Crear triggers de auditoria administrativa (aud_auditoria)',
        'V007__crear_triggers_auditoria.sql', CURRENT_USER());
