-- =============================================================================
-- V011__datos_iniciales_tipos_y_areas.sql
-- Sistema de seguimiento de solicitudes internas
-- Ejecuta: cuenta DBA (sis_migrador queda bloqueada tras el despliegue inicial)
-- Fuente: descripcion.md (soporte tecnico, materiales, mantenimiento, tramites),
--         aprobado por el usuario el 2026-10-07 (Practica 2).
-- Motivo: RF-13 deja tipos y areas a cargo del administrador, pero la pantalla
--         de administracion esta fuera del alcance de la Practica 2. Sin al
--         menos un tipo activo no se puede registrar una solicitud (RF-02).
-- Solo agrega filas. Se ejecuta en una transaccion: o se carga todo o nada.
-- =============================================================================

USE sis_solicitudes;

-- El archivo esta en UTF-8 y contiene tildes.
SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;

SET @sis_usuario_id = NULL;
SET @sis_ip = NULL;

START TRANSACTION;

-- -----------------------------------------------------------------------------
-- Tipos de solicitud (catalogo global, S-03)
-- -----------------------------------------------------------------------------
INSERT INTO cat_tipo_solicitud (nombre, descripcion) VALUES
  ('Soporte técnico',         'Problemas con equipos, sistemas o red'),
  ('Solicitud de materiales', 'Pedido de materiales o insumos'),
  ('Mantenimiento',           'Reparaciones de infraestructura o mobiliario'),
  ('Trámite administrativo',  'Certificados, permisos y otros trámites');

-- -----------------------------------------------------------------------------
-- Areas adicionales (TI ya existe desde V010). Sin jefe asignado (S-02).
-- -----------------------------------------------------------------------------
INSERT INTO org_area (codigo, nombre) VALUES
  ('MANT', 'Mantenimiento'),
  ('ADM',  'Administración'),
  ('ALM',  'Almacén');

INSERT INTO cfg_version_esquema (version, descripcion, script, aplicado_por)
VALUES ('V011', 'Datos iniciales: tipos de solicitud y areas adicionales',
        'V011__datos_iniciales_tipos_y_areas.sql', CURRENT_USER());

COMMIT;
