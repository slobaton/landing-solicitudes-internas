-- =============================================================================
-- V010__datos_iniciales_administrador.sql   (OPCIONAL)
-- Sistema de seguimiento de solicitudes internas
-- Ejecuta: sis_migrador
-- Fuente: requisitos_datos.md S-12 (cuentas creadas por el administrador);
--         seguridad.md §6 (hash adaptativo); versionamiento.md §5
--
-- Crea el area inicial y el primer usuario administrador para poder entrar
-- al sistema. Sin este usuario nadie puede iniciar sesion (S-12).
--
-- CONTIENE MARCADORES. NO EJECUTAR DIRECTAMENTE.
-- Sustituir en memoria desde .env con envsubst (lista explicita de variables)
-- y enviar por tuberia al cliente mysql. Procedimiento: plan_migraciones.md §3.
-- (Los comentarios no contienen marcadores a proposito.)
--
-- Variables de entorno requeridas:
--   PLACEHOLDERS_SUSTITUIDOS  debe valer SI
--   ADMIN_AREA_CODIGO         codigo del area del administrador (p. ej. TI)
--   ADMIN_AREA_NOMBRE         nombre del area
--   ADMIN_NOMBRE_USUARIO      nombre de usuario del administrador
--   ADMIN_NOMBRES             nombres
--   ADMIN_APELLIDOS           apellidos
--   ADMIN_CORREO              correo institucional
--   ADMIN_PASSWORD_HASH       HASH generado con password_hash() de PHP
--                             (PASSWORD_ARGON2ID o PASSWORD_BCRYPT).
--                             Nunca la contrasena en claro.
-- Los valores no deben contener comillas simples.
-- El administrador debe cambiar su contrasena en el primer inicio de sesion.
-- =============================================================================

USE sis_solicitudes;

SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;

-- Proteccion: aborta si los marcadores no fueron sustituidos.
DROP PROCEDURE IF EXISTS tmp_verificar_marcadores;
DELIMITER //
CREATE PROCEDURE tmp_verificar_marcadores(IN p_valor VARCHAR(40))
BEGIN
  IF p_valor IS NULL OR p_valor <> 'SI' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Marcadores sin sustituir: ejecutar con envsubst (ver plan_migraciones.md)';
  END IF;
END//
DELIMITER ;
CALL tmp_verificar_marcadores('${PLACEHOLDERS_SUSTITUIDOS}');
DROP PROCEDURE tmp_verificar_marcadores;

-- Carga inicial: auditoria con usuario_id NULL.
SET @sis_usuario_id = NULL;
SET @sis_ip = NULL;

START TRANSACTION;

-- Area inicial (se crea sin jefe: TRG-08)
INSERT INTO org_area (codigo, nombre)
VALUES ('${ADMIN_AREA_CODIGO}', '${ADMIN_AREA_NOMBRE}');

SET @v010_area_id = (SELECT id FROM org_area WHERE codigo = '${ADMIN_AREA_CODIGO}');

-- Primer administrador
INSERT INTO seg_usuario (nombre_usuario, nombres, apellidos, correo, password_hash, area_id)
VALUES ('${ADMIN_NOMBRE_USUARIO}', '${ADMIN_NOMBRES}', '${ADMIN_APELLIDOS}',
        '${ADMIN_CORREO}', '${ADMIN_PASSWORD_HASH}', @v010_area_id);

SET @v010_admin_id = (SELECT id FROM seg_usuario WHERE nombre_usuario = '${ADMIN_NOMBRE_USUARIO}');

-- Roles: todo usuario es SOLICITANTE (R02) + ADMINISTRADOR
INSERT INTO seg_usuario_rol (usuario_id, rol_id, asignado_por) VALUES
  (@v010_admin_id, 1, NULL),
  (@v010_admin_id, 4, NULL);

INSERT INTO cfg_version_esquema (version, descripcion, script, aplicado_por)
VALUES ('V010', 'Datos iniciales opcionales: area inicial y primer administrador',
        'V010__datos_iniciales_administrador.sql', CURRENT_USER());

COMMIT;
