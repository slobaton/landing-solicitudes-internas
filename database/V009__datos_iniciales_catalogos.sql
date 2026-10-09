-- =============================================================================
-- V009__datos_iniciales_catalogos.sql
-- Sistema de seguimiento de solicitudes internas
-- Ejecuta: sis_migrador
-- Fuente: modelo_conceptual.md §4 (T01..T10), modelo_logico.md §2 y §5,
--         modelo_fisico.md §3.2 (P-FIS-02), requisitos_datos.md S-07 y S-10,
--         perfil_carga.yaml (horario 08:30-16:30 lunes a viernes)
-- Los IDs de roles, estados, transiciones y prioridades son FIJOS (DF-05):
-- la aplicacion y los CHECK de sol_solicitud dependen de ellos.
-- Se ejecuta en una transaccion: o se carga todo o nada.
-- La auditoria de la carga queda con usuario_id NULL (carga inicial).
-- No incluye feriados (dependen del calendario oficial; revision_dba.md H-05).
-- =============================================================================

USE sis_solicitudes;

-- El archivo esta en UTF-8 y contiene tildes.
SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;

SET @sis_usuario_id = NULL;
SET @sis_ip = NULL;

START TRANSACTION;

-- -----------------------------------------------------------------------------
-- Roles (RD-02)
-- -----------------------------------------------------------------------------
INSERT INTO seg_rol (id, codigo, nombre) VALUES
  (1, 'SOLICITANTE',   'Solicitante'),
  (2, 'JEFE_AREA',     'Jefe de área'),
  (3, 'RESPONSABLE',   'Responsable'),
  (4, 'ADMINISTRADOR', 'Administrador');

-- -----------------------------------------------------------------------------
-- Estados (RD-06, RN-D11: finales = Cerrada, Rechazada, Cancelada)
-- -----------------------------------------------------------------------------
INSERT INTO cat_estado (id, codigo, nombre, es_final, orden) VALUES
  (1, 'REGISTRADA', 'Registrada', 0, 1),
  (2, 'ASIGNADA',   'Asignada',   0, 2),
  (3, 'EN_PROCESO', 'En proceso', 0, 3),
  (4, 'ATENDIDA',   'Atendida',   0, 4),
  (5, 'CERRADA',    'Cerrada',    1, 5),
  (6, 'RECHAZADA',  'Rechazada',  1, 6),
  (7, 'CANCELADA',  'Cancelada',  1, 7);

-- -----------------------------------------------------------------------------
-- Transiciones T01..T10 (RD-07; modelo_conceptual.md §4)
--   rol: 1 SOLICITANTE, 2 JEFE_AREA, 3 RESPONSABLE, NULL = sistema
--   T10 inactiva hasta aprobacion humana (S-10)
-- -----------------------------------------------------------------------------
INSERT INTO cat_transicion_estado
  (id, estado_origen_id, estado_destino_id, rol_id, requiere_motivo, es_automatica, activa) VALUES
  ( 1, 1, 2, 2,    0, 0, 1),   -- T01 Registrada -> Asignada    (jefe)
  ( 2, 1, 6, 2,    1, 0, 1),   -- T02 Registrada -> Rechazada   (jefe, motivo)
  ( 3, 1, 7, 1,    1, 0, 1),   -- T03 Registrada -> Cancelada   (solicitante, motivo)
  ( 4, 2, 3, 3,    0, 0, 1),   -- T04 Asignada   -> En proceso  (responsable)
  ( 5, 2, 6, 2,    1, 0, 1),   -- T05 Asignada   -> Rechazada   (jefe, motivo)
  ( 6, 2, 7, 1,    1, 0, 1),   -- T06 Asignada   -> Cancelada   (solicitante, motivo)
  ( 7, 3, 4, 3,    0, 0, 1),   -- T07 En proceso -> Atendida    (responsable)
  ( 8, 4, 5, 1,    0, 0, 1),   -- T08 Atendida   -> Cerrada     (solicitante confirma)
  ( 9, 4, 5, NULL, 0, 1, 1),   -- T09 Atendida   -> Cerrada     (sistema, cierre automatico)
  (10, 4, 3, 1,    1, 0, 0);   -- T10 Atendida   -> En proceso  (solicitante no conforme) INACTIVA

-- -----------------------------------------------------------------------------
-- Prioridades (RD-05). Horas laborales propuestas (P-FIS-02, revision_dba.md H-07);
-- el administrador puede ajustarlas sin migracion.
-- -----------------------------------------------------------------------------
INSERT INTO cat_prioridad (id, codigo, nombre, orden, horas_max_atencion, activo) VALUES
  (1, 'BAJA',    'Baja',    1, 40, 1),
  (2, 'MEDIA',   'Media',   2, 24, 1),
  (3, 'ALTA',    'Alta',    3,  8, 1),
  (4, 'URGENTE', 'Urgente', 4,  4, 1);

-- -----------------------------------------------------------------------------
-- Tipos de archivo permitidos (RD-13, RN-D14): PDF, imagenes y Office
-- -----------------------------------------------------------------------------
INSERT INTO cfg_tipo_archivo (extension, mime_type, activo) VALUES
  ('pdf',  'application/pdf', 1),
  ('jpg',  'image/jpeg', 1),
  ('jpeg', 'image/jpeg', 1),
  ('png',  'image/png', 1),
  ('doc',  'application/msword', 1),
  ('docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 1),
  ('xls',  'application/vnd.ms-excel', 1),
  ('xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 1),
  ('ppt',  'application/vnd.ms-powerpoint', 1),
  ('pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 1);

-- -----------------------------------------------------------------------------
-- Horario laboral (RD-16; perfil_carga.yaml): lunes a viernes 08:30-16:30.
-- Sabado y domingo inactivos (las horas son requeridas por el CHECK).
-- -----------------------------------------------------------------------------
INSERT INTO cfg_horario_laboral (dia_semana, hora_inicio, hora_fin, activo) VALUES
  (1, '08:30:00', '16:30:00', 1),
  (2, '08:30:00', '16:30:00', 1),
  (3, '08:30:00', '16:30:00', 1),
  (4, '08:30:00', '16:30:00', 1),
  (5, '08:30:00', '16:30:00', 1),
  (6, '08:30:00', '16:30:00', 0),
  (7, '08:30:00', '16:30:00', 0);

-- -----------------------------------------------------------------------------
-- Parametros (RD-20, S-07)
-- -----------------------------------------------------------------------------
INSERT INTO cfg_parametro (clave, valor, tipo_dato, descripcion) VALUES
  ('ADJUNTO_TAMANO_MAX_MB',          '10', 'ENTERO', 'Tamaño máximo de cada archivo adjunto, en MB'),
  ('CIERRE_AUTOMATICO_DIAS_HABILES', '3',  'ENTERO', 'Días hábiles en Atendida antes del cierre automático'),
  ('CORREO_MAX_INTENTOS',            '5',  'ENTERO', 'Intentos de envío de un correo antes de marcarlo FALLIDO');

INSERT INTO cfg_version_esquema (version, descripcion, script, aplicado_por)
VALUES ('V009', 'Datos iniciales: roles, estados, transiciones, prioridades, tipos de archivo, horario y parametros',
        'V009__datos_iniciales_catalogos.sql', CURRENT_USER());

COMMIT;
