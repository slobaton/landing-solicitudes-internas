-- =============================================================================
-- V003__crear_tablas.sql  (SCRIPT PRINCIPAL DE CREACION DE TABLAS)
-- Sistema de seguimiento de solicitudes internas
-- Motor: MySQL 8.4 LTS / InnoDB (compatible con 8.0.19+)
-- Ejecuta: sis_migrador
--
-- Contenido: 20 tablas de negocio con PK, UNIQUE, CHECK, NOT NULL y DEFAULT.
-- Los indices secundarios se crean en V004 y las FK en V005 (orden necesario
-- por la referencia circular org_area <-> seg_usuario y para que las FK usen
-- indices con nombre propio).
--
-- Fuentes: 03_modelo_logico/modelo_logico.md, 05_modelo_fisico/modelo_fisico.md,
--          06_integridad/integridad.md (correcciones CI-01..CI-09).
-- IDs fijos de cat_estado usados en CHECK (integridad.md §3):
--   1 REGISTRADA, 2 ASIGNADA, 3 EN_PROCESO, 4 ATENDIDA,
--   5 CERRADA, 6 RECHAZADA, 7 CANCELADA
-- =============================================================================

USE sis_solicitudes;

-- -----------------------------------------------------------------------------
-- 1. SEGURIDAD
-- -----------------------------------------------------------------------------

-- RD-01, RF-01, RF-13, RNF-01
CREATE TABLE seg_usuario (
  id                INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  nombre_usuario    VARCHAR(50)       NOT NULL,
  nombres           VARCHAR(100)      NOT NULL,
  apellidos         VARCHAR(100)      NOT NULL,
  correo            VARCHAR(150)      NOT NULL,
  password_hash     VARCHAR(255)      CHARACTER SET ascii COLLATE ascii_bin NOT NULL
                    COMMENT 'Hash adaptativo (argon2id/bcrypt). Nunca texto plano (RNF-01)',
  area_id           SMALLINT UNSIGNED NOT NULL,
  activo            TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  ultimo_acceso_en  DATETIME          NULL DEFAULT NULL,
  creado_en         DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_seg_usuario PRIMARY KEY (id),
  CONSTRAINT uq_seg_usuario_nombre_usuario UNIQUE (nombre_usuario),
  CONSTRAINT uq_seg_usuario_correo UNIQUE (correo),
  CONSTRAINT ck_seg_usuario_activo CHECK (activo IN (0, 1)),
  CONSTRAINT ck_seg_usuario_correo CHECK (correo LIKE '%_@_%._%'),
  CONSTRAINT ck_seg_usuario_nombre_usuario CHECK (CHAR_LENGTH(TRIM(nombre_usuario)) >= 3),
  CONSTRAINT ck_seg_usuario_nombres CHECK (CHAR_LENGTH(TRIM(nombres)) > 0 AND CHAR_LENGTH(TRIM(apellidos)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Funcionarios con cuenta. Inactivacion logica (R-04)';

-- RD-02. Catalogo fijo: 1 SOLICITANTE, 2 JEFE_AREA, 3 RESPONSABLE, 4 ADMINISTRADOR
CREATE TABLE seg_rol (
  id      TINYINT UNSIGNED NOT NULL,
  codigo  VARCHAR(20)      CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  nombre  VARCHAR(50)      NOT NULL,
  CONSTRAINT pk_seg_rol PRIMARY KEY (id),
  CONSTRAINT uq_seg_rol_codigo UNIQUE (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Roles de la aplicacion (catalogo fijo, solo por migracion)';

-- RD-02 (N:M usuario-rol, S-01)
CREATE TABLE seg_usuario_rol (
  usuario_id    INT UNSIGNED     NOT NULL,
  rol_id        TINYINT UNSIGNED NOT NULL,
  asignado_en   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  asignado_por  INT UNSIGNED     NULL DEFAULT NULL COMMENT 'Nulo en la carga inicial',
  CONSTRAINT pk_seg_usuario_rol PRIMARY KEY (usuario_id, rol_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Roles asignados a cada usuario';

-- -----------------------------------------------------------------------------
-- 2. ORGANIZACION Y CATALOGOS
-- -----------------------------------------------------------------------------

-- RD-03, RF-04, RF-13, S-02
CREATE TABLE org_area (
  id               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo           VARCHAR(20)       CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  nombre           VARCHAR(100)      NOT NULL,
  jefe_usuario_id  INT UNSIGNED      NULL DEFAULT NULL COMMENT 'Nulo mientras no haya jefe (S-02)',
  activo           TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  creado_en        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_org_area PRIMARY KEY (id),
  CONSTRAINT uq_org_area_codigo UNIQUE (codigo),
  CONSTRAINT uq_org_area_nombre UNIQUE (nombre),
  CONSTRAINT uq_org_area_jefe UNIQUE (jefe_usuario_id),
  CONSTRAINT ck_org_area_activo CHECK (activo IN (0, 1)),
  CONSTRAINT ck_org_area_codigo CHECK (CHAR_LENGTH(TRIM(codigo)) > 0),
  CONSTRAINT ck_org_area_nombre CHECK (CHAR_LENGTH(TRIM(nombre)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Areas de la institucion. Inactivacion logica (R-04)';

-- RD-04, RF-02, RF-13, S-03
CREATE TABLE cat_tipo_solicitud (
  id              SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(100)      NOT NULL,
  descripcion     VARCHAR(255)      NULL DEFAULT NULL,
  activo          TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  creado_en       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_cat_tipo_solicitud PRIMARY KEY (id),
  CONSTRAINT uq_cat_tipo_solicitud_nombre UNIQUE (nombre),
  CONSTRAINT ck_cat_tipo_solicitud_activo CHECK (activo IN (0, 1)),
  CONSTRAINT ck_cat_tipo_solicitud_nombre CHECK (CHAR_LENGTH(TRIM(nombre)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Tipos de solicitud (catalogo global). Inactivacion logica';

-- RD-05, RF-02, RF-11. Filas fijas 1..4; horas editables por el administrador
CREATE TABLE cat_prioridad (
  id                  TINYINT UNSIGNED  NOT NULL,
  codigo              VARCHAR(10)       CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  nombre              VARCHAR(30)       NOT NULL,
  orden               TINYINT UNSIGNED  NOT NULL,
  horas_max_atencion  SMALLINT UNSIGNED NOT NULL COMMENT 'Horas laborales maximas de atencion',
  activo              TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  actualizado_en      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_cat_prioridad PRIMARY KEY (id),
  CONSTRAINT uq_cat_prioridad_codigo UNIQUE (codigo),
  CONSTRAINT uq_cat_prioridad_orden UNIQUE (orden),
  CONSTRAINT ck_cat_prioridad_orden CHECK (orden > 0),
  CONSTRAINT ck_cat_prioridad_horas CHECK (horas_max_atencion > 0),
  CONSTRAINT ck_cat_prioridad_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Prioridades con tiempo maximo de atencion (RN-D08)';

-- RD-06, RF-05, RF-14. Catalogo fijo (DF-05)
CREATE TABLE cat_estado (
  id        TINYINT UNSIGNED NOT NULL,
  codigo    VARCHAR(15)      CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  nombre    VARCHAR(30)      NOT NULL,
  es_final  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  orden     TINYINT UNSIGNED NOT NULL,
  CONSTRAINT pk_cat_estado PRIMARY KEY (id),
  CONSTRAINT uq_cat_estado_codigo UNIQUE (codigo),
  CONSTRAINT uq_cat_estado_orden UNIQUE (orden),
  CONSTRAINT ck_cat_estado_es_final CHECK (es_final IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Estados de la solicitud (ids fijos, solo por migracion)';

-- RD-07, RF-04, RF-05, RF-14. Transiciones T01..T10 (ids fijos)
CREATE TABLE cat_transicion_estado (
  id                 SMALLINT UNSIGNED NOT NULL,
  estado_origen_id   TINYINT UNSIGNED  NOT NULL,
  estado_destino_id  TINYINT UNSIGNED  NOT NULL,
  rol_id             TINYINT UNSIGNED  NULL DEFAULT NULL COMMENT 'Nulo = transicion automatica del sistema',
  rol_clave          TINYINT UNSIGNED  AS (IFNULL(rol_id, 0)) STORED
                     COMMENT 'CI-02: permite UNIQUE con rol nulo',
  requiere_motivo    TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  es_automatica      TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  activa             TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  CONSTRAINT pk_cat_transicion_estado PRIMARY KEY (id),
  CONSTRAINT uq_cat_transicion UNIQUE (estado_origen_id, estado_destino_id, rol_clave),
  CONSTRAINT uq_cat_transicion_estados UNIQUE (id, estado_origen_id, estado_destino_id),
  CONSTRAINT ck_cat_transicion_distinta CHECK (estado_origen_id <> estado_destino_id),
  CONSTRAINT ck_cat_transicion_actor CHECK (
    (rol_id IS NOT NULL AND es_automatica = 0) OR (rol_id IS NULL AND es_automatica = 1)
  ),
  CONSTRAINT ck_cat_transicion_requiere_motivo CHECK (requiere_motivo IN (0, 1)),
  CONSTRAINT ck_cat_transicion_es_automatica CHECK (es_automatica IN (0, 1)),
  CONSTRAINT ck_cat_transicion_activa CHECK (activa IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Maquina de estados: transiciones permitidas por rol';

-- -----------------------------------------------------------------------------
-- 3. SOLICITUDES
-- -----------------------------------------------------------------------------

-- RD-09, RF-03, S-11
CREATE TABLE sol_secuencia (
  anio          SMALLINT UNSIGNED NOT NULL,
  ultimo_valor  INT UNSIGNED      NOT NULL DEFAULT 0,
  CONSTRAINT pk_sol_secuencia PRIMARY KEY (anio),
  CONSTRAINT ck_sol_secuencia_anio CHECK (anio BETWEEN 2020 AND 2100),
  CONSTRAINT ck_sol_secuencia_tope CHECK (ultimo_valor <= 99999)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Secuencia anual del numero AAAA-NNNNN';

-- RD-08, RD-17, RD-18; RF-02..RF-05, RF-10..RF-12, RF-14
CREATE TABLE sol_solicitud (
  id                     BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  numero                 CHAR(10)          CHARACTER SET ascii COLLATE ascii_bin NOT NULL
                         COMMENT 'AAAA-NNNNN (RF-03, S-11)',
  titulo                 VARCHAR(150)      NOT NULL,
  descripcion            TEXT              NOT NULL,
  solicitante_id         INT UNSIGNED      NOT NULL,
  area_destino_id        SMALLINT UNSIGNED NOT NULL,
  tipo_solicitud_id      SMALLINT UNSIGNED NOT NULL,
  prioridad_id           TINYINT UNSIGNED  NOT NULL,
  estado_id              TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  responsable_id         INT UNSIGNED      NULL DEFAULT NULL,
  fecha_registro         DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_limite           DATETIME          NOT NULL COMMENT 'Calculada por prioridad, horario y feriados (D-05)',
  fecha_atencion         DATETIME          NULL DEFAULT NULL,
  fecha_cierre           DATETIME          NULL DEFAULT NULL,
  horas_atencion         DECIMAL(7,2)      NULL DEFAULT NULL COMMENT 'Horas laborales Registrada -> Atendida (D-02)',
  alerta_vencimiento_en  DATETIME          NULL DEFAULT NULL COMMENT 'Alerta emitida (RC-10)',
  version                INT UNSIGNED      NOT NULL DEFAULT 0 COMMENT 'Concurrencia optimista (DF-06)',
  creado_en              DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en         DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_sol_solicitud PRIMARY KEY (id),
  CONSTRAINT uq_sol_solicitud_numero UNIQUE (numero),
  CONSTRAINT ck_sol_solicitud_numero_formato CHECK (numero REGEXP '^[0-9]{4}-[0-9]{5}$'),
  CONSTRAINT ck_sol_solicitud_numero_anio CHECK (CAST(LEFT(numero, 4) AS UNSIGNED) = YEAR(fecha_registro)),
  CONSTRAINT ck_sol_solicitud_titulo CHECK (CHAR_LENGTH(TRIM(titulo)) > 0),
  CONSTRAINT ck_sol_solicitud_limite CHECK (fecha_limite > fecha_registro),
  CONSTRAINT ck_sol_solicitud_fecha_atencion CHECK (fecha_atencion IS NULL OR fecha_atencion >= fecha_registro),
  CONSTRAINT ck_sol_solicitud_fecha_cierre CHECK (fecha_cierre IS NULL OR fecha_cierre >= fecha_registro),
  CONSTRAINT ck_sol_solicitud_horas CHECK (horas_atencion IS NULL OR horas_atencion >= 0),
  CONSTRAINT ck_sol_solicitud_atencion_coherente CHECK ((fecha_atencion IS NULL) = (horas_atencion IS NULL)),
  CONSTRAINT ck_sol_solicitud_registrada_sin_resp CHECK (estado_id <> 1 OR responsable_id IS NULL),
  CONSTRAINT ck_sol_solicitud_resp_obligatorio CHECK (estado_id NOT IN (2, 3, 4) OR responsable_id IS NOT NULL),
  CONSTRAINT ck_sol_solicitud_cierre_estado CHECK ((estado_id IN (5, 6, 7)) = (fecha_cierre IS NOT NULL)),
  CONSTRAINT ck_sol_solicitud_atencion_estado CHECK (estado_id NOT IN (4, 5) OR fecha_atencion IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Solicitudes internas (entidad central). Sin borrado fisico (R-06)';

-- RD-10, RF-05, RF-09, RNF-05. Solo insercion (RN-D13)
CREATE TABLE sol_historial (
  id                       BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  solicitud_id             BIGINT UNSIGNED   NOT NULL,
  tipo_evento              VARCHAR(20)       CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  usuario_id               INT UNSIGNED      NULL DEFAULT NULL COMMENT 'Nulo solo si el actor es el sistema (T09)',
  transicion_id            SMALLINT UNSIGNED NULL DEFAULT NULL,
  estado_anterior_id       TINYINT UNSIGNED  NULL DEFAULT NULL,
  estado_nuevo_id          TINYINT UNSIGNED  NULL DEFAULT NULL,
  responsable_anterior_id  INT UNSIGNED      NULL DEFAULT NULL,
  responsable_nuevo_id     INT UNSIGNED      NULL DEFAULT NULL,
  prioridad_anterior_id    TINYINT UNSIGNED  NULL DEFAULT NULL,
  prioridad_nueva_id       TINYINT UNSIGNED  NULL DEFAULT NULL,
  motivo                   VARCHAR(500)      NULL DEFAULT NULL,
  detalle                  JSON              NULL,
  ip_origen                VARCHAR(45)       CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  fecha                    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_sol_historial PRIMARY KEY (id),
  CONSTRAINT ck_sol_historial_tipo CHECK (
    tipo_evento IN ('REGISTRO', 'EDICION', 'ASIGNACION', 'CAMBIO_ESTADO', 'CAMBIO_PRIORIDAD')
  ),
  CONSTRAINT ck_sol_historial_transicion CHECK ((transicion_id IS NULL) = (tipo_evento <> 'CAMBIO_ESTADO')),
  CONSTRAINT ck_sol_historial_cambio_estado CHECK (
    tipo_evento <> 'CAMBIO_ESTADO'
    OR (transicion_id IS NOT NULL AND estado_anterior_id IS NOT NULL AND estado_nuevo_id IS NOT NULL)
  ),
  CONSTRAINT ck_sol_historial_asignacion CHECK (tipo_evento <> 'ASIGNACION' OR responsable_nuevo_id IS NOT NULL),
  CONSTRAINT ck_sol_historial_prioridad CHECK (
    tipo_evento <> 'CAMBIO_PRIORIDAD'
    OR (prioridad_anterior_id IS NOT NULL AND prioridad_nueva_id IS NOT NULL)
  ),
  CONSTRAINT ck_sol_historial_edicion CHECK (tipo_evento <> 'EDICION' OR detalle IS NOT NULL),
  CONSTRAINT ck_sol_historial_registro CHECK (tipo_evento <> 'REGISTRO' OR estado_nuevo_id IS NOT NULL),
  CONSTRAINT ck_sol_historial_actor CHECK (usuario_id IS NOT NULL OR tipo_evento = 'CAMBIO_ESTADO')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Historial inmutable de eventos de la solicitud (RNF-05)';

-- RD-11, RF-06. Solo insercion
CREATE TABLE sol_comentario (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  solicitud_id  BIGINT UNSIGNED NOT NULL,
  usuario_id    INT UNSIGNED    NOT NULL,
  tipo          VARCHAR(12)     CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  texto         TEXT            NOT NULL,
  fecha         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_sol_comentario PRIMARY KEY (id),
  CONSTRAINT ck_sol_comentario_tipo CHECK (tipo IN ('COMENTARIO', 'ACCION')),
  CONSTRAINT ck_sol_comentario_texto CHECK (CHAR_LENGTH(TRIM(texto)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Comentarios y acciones del responsable (visibles al solicitante, S-06)';

-- RD-12, RF-07. Solo insercion (S-08). El archivo vive fuera de la BD (DF-04)
CREATE TABLE sol_adjunto (
  id                 BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  solicitud_id       BIGINT UNSIGNED   NOT NULL,
  usuario_id         INT UNSIGNED      NOT NULL,
  tipo_archivo_id    SMALLINT UNSIGNED NOT NULL,
  nombre_original    VARCHAR(255)      NOT NULL COMMENT 'Solo para mostrar; nunca para la ruta',
  nombre_almacenado  CHAR(64)          CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  mime_type          VARCHAR(100)      CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL
                     COMMENT 'MIME real detectado del archivo',
  tamano_bytes       INT UNSIGNED      NOT NULL,
  hash_sha256        CHAR(64)          CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  fecha              DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_sol_adjunto PRIMARY KEY (id),
  CONSTRAINT uq_sol_adjunto_nombre_almacenado UNIQUE (nombre_almacenado),
  CONSTRAINT ck_sol_adjunto_tamano CHECK (tamano_bytes > 0),
  CONSTRAINT ck_sol_adjunto_hash CHECK (hash_sha256 REGEXP '^[0-9a-f]{64}$'),
  CONSTRAINT ck_sol_adjunto_nombre_almacenado CHECK (nombre_almacenado REGEXP '^[0-9a-f]{64}$'),
  CONSTRAINT ck_sol_adjunto_nombre_original CHECK (CHAR_LENGTH(TRIM(nombre_original)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Metadatos de archivos adjuntos';

-- -----------------------------------------------------------------------------
-- 4. NOTIFICACIONES
-- -----------------------------------------------------------------------------

-- RD-14, RF-08, RF-11
CREATE TABLE not_notificacion (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id    INT UNSIGNED    NOT NULL COMMENT 'Destinatario',
  solicitud_id  BIGINT UNSIGNED NULL DEFAULT NULL,
  tipo          VARCHAR(20)     CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  titulo        VARCHAR(150)    NOT NULL,
  mensaje       VARCHAR(1000)   NOT NULL,
  creada_en     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  leida_en      DATETIME        NULL DEFAULT NULL COMMENT 'Nulo = no leida',
  CONSTRAINT pk_not_notificacion PRIMARY KEY (id),
  CONSTRAINT ck_not_notificacion_tipo CHECK (
    tipo IN ('NUEVA_SOLICITUD', 'ASIGNACION', 'CAMBIO_ESTADO', 'VENCIMIENTO', 'COMENTARIO')
  ),
  CONSTRAINT ck_not_notificacion_leida CHECK (leida_en IS NULL OR leida_en >= creada_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Notificaciones dentro del sistema';

-- RD-15, RF-08. Cola de salida para el cron
CREATE TABLE not_correo (
  id                  BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  notificacion_id     BIGINT UNSIGNED  NOT NULL,
  destinatario        VARCHAR(150)     NOT NULL COMMENT 'Copia historica del correo (D-04)',
  asunto              VARCHAR(200)     NOT NULL,
  cuerpo              TEXT             NOT NULL,
  estado              VARCHAR(10)      CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PENDIENTE',
  intentos            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  proximo_intento_en  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_error        VARCHAR(500)     NULL DEFAULT NULL,
  enviado_en          DATETIME         NULL DEFAULT NULL,
  creado_en           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_not_correo PRIMARY KEY (id),
  CONSTRAINT uq_not_correo_notificacion UNIQUE (notificacion_id),
  CONSTRAINT ck_not_correo_estado CHECK (estado IN ('PENDIENTE', 'ENVIADO', 'FALLIDO')),
  CONSTRAINT ck_not_correo_enviado CHECK ((estado = 'ENVIADO') = (enviado_en IS NOT NULL)),
  CONSTRAINT ck_not_correo_destinatario CHECK (destinatario LIKE '%_@_%._%')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Cola de correos (FOR UPDATE SKIP LOCKED)';

-- -----------------------------------------------------------------------------
-- 5. CONFIGURACION
-- -----------------------------------------------------------------------------

-- RD-16. 1 = lunes ... 7 = domingo (ISO-8601)
CREATE TABLE cfg_horario_laboral (
  dia_semana   TINYINT UNSIGNED NOT NULL,
  hora_inicio  TIME             NOT NULL,
  hora_fin     TIME             NOT NULL,
  activo       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT pk_cfg_horario_laboral PRIMARY KEY (dia_semana),
  CONSTRAINT ck_cfg_horario_dia CHECK (dia_semana BETWEEN 1 AND 7),
  CONSTRAINT ck_cfg_horario_rango CHECK (hora_fin > hora_inicio),
  CONSTRAINT ck_cfg_horario_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Horario laboral por dia de la semana (calculo de horas laborales)';

-- RD-16
CREATE TABLE cfg_feriado (
  fecha        DATE         NOT NULL,
  descripcion  VARCHAR(100) NOT NULL,
  CONSTRAINT pk_cfg_feriado PRIMARY KEY (fecha),
  CONSTRAINT ck_cfg_feriado_descripcion CHECK (CHAR_LENGTH(TRIM(descripcion)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Dias no laborables';

-- RD-13, RF-07, RN-D14
CREATE TABLE cfg_tipo_archivo (
  id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  extension  VARCHAR(10)       CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Minusculas, sin punto',
  mime_type  VARCHAR(100)      CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  activo     TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  CONSTRAINT pk_cfg_tipo_archivo PRIMARY KEY (id),
  CONSTRAINT uq_cfg_tipo_archivo_extension UNIQUE (extension),
  CONSTRAINT ck_cfg_tipo_archivo_extension CHECK (REGEXP_LIKE(extension, '^[a-z0-9]+$', 'c')),
  CONSTRAINT ck_cfg_tipo_archivo_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Tipos de archivo permitidos para adjuntos';

-- RD-20, S-07
CREATE TABLE cfg_parametro (
  clave            VARCHAR(60)  CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  valor            VARCHAR(255) NOT NULL,
  tipo_dato        VARCHAR(10)  CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  descripcion      VARCHAR(255) NOT NULL,
  actualizado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  actualizado_por  INT UNSIGNED NULL DEFAULT NULL,
  CONSTRAINT pk_cfg_parametro PRIMARY KEY (clave),
  CONSTRAINT ck_cfg_parametro_tipo CHECK (tipo_dato IN ('ENTERO', 'DECIMAL', 'TEXTO', 'BOOLEANO')),
  CONSTRAINT ck_cfg_parametro_valor CHECK (
    (tipo_dato <> 'ENTERO'   OR valor REGEXP '^-?[0-9]+$')
    AND (tipo_dato <> 'DECIMAL'  OR valor REGEXP '^-?[0-9]+([.][0-9]+)?$')
    AND (tipo_dato <> 'BOOLEANO' OR valor IN ('0', '1'))
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Parametros configurables del sistema';

-- -----------------------------------------------------------------------------
-- 6. AUDITORIA
-- -----------------------------------------------------------------------------

-- RD-19, RNF-05, R-05. Solo insercion. CI-01: accion VARCHAR(15) + ELIMINAR
CREATE TABLE aud_auditoria (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entidad             VARCHAR(40)     CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Tabla afectada',
  registro_id         VARCHAR(40)     NOT NULL COMMENT 'Clave del registro como texto',
  accion              VARCHAR(15)     CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  usuario_id          INT UNSIGNED    NULL DEFAULT NULL,
  valores_anteriores  JSON            NULL,
  valores_nuevos      JSON            NULL,
  ip_origen           VARCHAR(45)     CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  fecha               DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_aud_auditoria PRIMARY KEY (id),
  CONSTRAINT ck_aud_auditoria_accion CHECK (
    accion IN ('CREAR', 'MODIFICAR', 'INACTIVAR', 'ACTIVAR', 'ELIMINAR',
               'ASIGNAR_ROL', 'QUITAR_ROL', 'LOGIN', 'LOGIN_FALLIDO')
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Auditoria inmutable de datos administrativos y accesos';

INSERT INTO cfg_version_esquema (version, descripcion, script, aplicado_por)
VALUES ('V003', 'Crear 20 tablas de negocio con PK, UNIQUE, CHECK, NOT NULL y DEFAULT',
        'V003__crear_tablas.sql', CURRENT_USER());
