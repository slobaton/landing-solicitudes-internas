-- =============================================================================
-- V001__crear_base_datos.sql
-- Sistema de seguimiento de solicitudes internas
-- Motor: MySQL 8.4 LTS / InnoDB (compatible con 8.0.19+)
-- Ejecuta: cuenta DBA institucional (requiere CREATE)
-- Fuente: 09_versionamiento/versionamiento.md §3 y §5; 05_modelo_fisico/modelo_fisico.md §1
-- Revision DBA: 15_reportes/revision_dba.md (STATUS: APPROVED)
-- =============================================================================

-- La base puede haber sido creada previamente por el DBA (servidor compartido,
-- P-DBMS-02): unico objeto con IF NOT EXISTS (versionamiento.md R-V06).
CREATE DATABASE IF NOT EXISTS sis_solicitudes
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

-- Si ya existia, se asegura el juego de caracteres por defecto.
ALTER DATABASE sis_solicitudes
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

USE sis_solicitudes;

-- Tabla de control de versiones del esquema (CI-08).
-- Sin IF NOT EXISTS: si existe, hay deriva o doble ejecucion y debe fallar.
CREATE TABLE cfg_version_esquema (
  version          VARCHAR(10)  CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  descripcion      VARCHAR(200) NOT NULL,
  script           VARCHAR(150) NOT NULL,
  checksum_sha256  CHAR(64)     CHARACTER SET ascii COLLATE ascii_bin NULL,
  aplicado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aplicado_por     VARCHAR(288) NOT NULL,
  CONSTRAINT pk_cfg_version_esquema PRIMARY KEY (version),
  CONSTRAINT ck_cfg_version_esquema_formato CHECK (version REGEXP '^V[0-9]{3}$')
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Control de versiones del esquema (V###). Ver 09_versionamiento/versionamiento.md';

INSERT INTO cfg_version_esquema (version, descripcion, script, aplicado_por)
VALUES ('V001', 'Crear base de datos y tabla de control de versiones',
        'V001__crear_base_datos.sql', CURRENT_USER());
