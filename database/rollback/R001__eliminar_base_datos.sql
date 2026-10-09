-- =============================================================================
-- R001__eliminar_base_datos.sql   (reversion de V001)
--
-- *** OPERACION DESTRUCTIVA: DROP DATABASE sis_solicitudes.           ***
-- *** Se pierde la base completa. Requiere autorizacion explicita y   ***
-- *** por escrito (workflow 02, paso 17) y un respaldo verificado.    ***
-- *** No forma parte de ningun procedimiento automatico.              ***
--
-- Ejecuta: cuenta DBA institucional.
-- Aplicar antes R008 y R002 para no dejar cuentas y roles huerfanos
-- (las cuentas no pertenecen a la base y DROP DATABASE no las elimina).
-- =============================================================================

DROP DATABASE IF EXISTS sis_solicitudes;
