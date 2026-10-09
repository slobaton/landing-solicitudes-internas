-- =============================================================================
-- R003__eliminar_tablas.sql   (reversion de V003)
--
-- *** OPERACION DESTRUCTIVA: DROP TABLE de las 20 tablas de negocio. ***
-- *** Se pierden TODOS los datos. Requiere autorizacion explicita y   ***
-- *** por escrito (workflow 02, paso 17) y un respaldo verificado.    ***
-- *** No forma parte de ningun procedimiento automatico.              ***
--
-- Ejecuta: sis_migrador. Aplicar antes R004..R010 segun corresponda.
-- Orden: tablas hijas primero, para que funcione aunque no se haya
-- ejecutado R005. Re-ejecutable: DROP TABLE IF EXISTS.
-- cfg_version_esquema se conserva (pertenece a V001).
-- =============================================================================

USE sis_solicitudes;

DROP TABLE IF EXISTS not_correo;
DROP TABLE IF EXISTS not_notificacion;
DROP TABLE IF EXISTS sol_adjunto;
DROP TABLE IF EXISTS sol_comentario;
DROP TABLE IF EXISTS sol_historial;
DROP TABLE IF EXISTS sol_solicitud;
DROP TABLE IF EXISTS sol_secuencia;
DROP TABLE IF EXISTS aud_auditoria;
DROP TABLE IF EXISTS cfg_parametro;
DROP TABLE IF EXISTS cfg_tipo_archivo;
DROP TABLE IF EXISTS cfg_feriado;
DROP TABLE IF EXISTS cfg_horario_laboral;
DROP TABLE IF EXISTS cat_transicion_estado;
DROP TABLE IF EXISTS cat_estado;
DROP TABLE IF EXISTS cat_prioridad;
DROP TABLE IF EXISTS cat_tipo_solicitud;
DROP TABLE IF EXISTS seg_usuario_rol;
-- Referencia circular org_area <-> seg_usuario: si R005 no se aplico,
-- se elimina primero la FK del area hacia el jefe.
SET @tmp_existe_fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                       WHERE constraint_schema = DATABASE()
                         AND table_name = 'org_area'
                         AND constraint_name = 'fk_org_area_jefe');
SET @tmp_sql = IF(@tmp_existe_fk > 0,
                  'ALTER TABLE org_area DROP FOREIGN KEY fk_org_area_jefe',
                  'DO 0');
PREPARE tmp_stmt FROM @tmp_sql;
EXECUTE tmp_stmt;
DEALLOCATE PREPARE tmp_stmt;
DROP TABLE IF EXISTS seg_usuario;
DROP TABLE IF EXISTS org_area;
DROP TABLE IF EXISTS seg_rol;

DELETE FROM cfg_version_esquema WHERE version = 'V003';
