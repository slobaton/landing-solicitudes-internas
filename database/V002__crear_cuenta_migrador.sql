-- =============================================================================
-- V002__crear_cuenta_migrador.sql
-- Sistema de seguimiento de solicitudes internas
-- Ejecuta: cuenta DBA institucional (requiere CREATE USER y GRANT OPTION)
-- Fuente: 07_seguridad/seguridad.md §2 y §3; 12_migraciones/plan_migraciones.md §2-§3
--
-- CONTIENE MARCADORES. NO EJECUTAR DIRECTAMENTE.
-- Sustituir en memoria desde .env con envsubst (lista explicita de variables)
-- y enviar por tuberia al cliente mysql. Nunca guardar ni versionar el archivo
-- sustituido. Procedimiento: 12_migraciones/plan_migraciones.md §3.
-- (Los comentarios no contienen marcadores a proposito: envsubst tambien los
-- sustituiria.)
--
-- Variables de entorno requeridas:
--   PLACEHOLDERS_SUSTITUIDOS  debe valer SI
--   SIS_MIGRADOR_HOST         host desde el que se ejecutan las migraciones
--   SIS_MIGRADOR_PASSWORD     contrasena generada aleatoriamente (>= 16 caracteres)
-- =============================================================================

USE sis_solicitudes;

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

-- Cuenta de migracion: privilegios directos (sin rol) porque es el DEFINER
-- de los triggers (seguridad.md §2).
CREATE USER IF NOT EXISTS 'sis_migrador'@'${SIS_MIGRADOR_HOST}'
  IDENTIFIED WITH caching_sha2_password BY '${SIS_MIGRADOR_PASSWORD}'
  REQUIRE SSL
  WITH MAX_USER_CONNECTIONS 3
  FAILED_LOGIN_ATTEMPTS 5 PASSWORD_LOCK_TIME 1;

GRANT CREATE, ALTER, DROP, INDEX, REFERENCES, TRIGGER,
      CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EXECUTE,
      LOCK TABLES, SELECT, INSERT, UPDATE, DELETE
  ON sis_solicitudes.*
  TO 'sis_migrador'@'${SIS_MIGRADOR_HOST}';

INSERT INTO cfg_version_esquema (version, descripcion, script, aplicado_por)
VALUES ('V002', 'Crear cuenta de migracion sis_migrador',
        'V002__crear_cuenta_migrador.sql', CURRENT_USER());
