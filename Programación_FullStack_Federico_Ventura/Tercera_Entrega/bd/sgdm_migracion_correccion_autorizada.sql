-- ============================================================
-- sgdm_migracion_correccion_autorizada.sql
-- Se corre DESPUÉS de sgdm_migracion_modulos_config.sql.
--
-- La letra (5.2) dice que el Organizador puede corregir resultados
-- "si cuenta con autorización". La autorización la da el Administrador
-- general torneo por torneo:
--
--   torneos.correccion_autorizada = 0  -> el organizador asignado carga resultados
--                                         pero NO puede corregir los ya cargados
--   torneos.correccion_autorizada = 1  -> el organizador asignado también puede corregir
--
-- El Administrador general siempre puede corregir, esté o no autorizado el
-- organizador. Cada vez que se habilita o se quita la autorización queda un
-- renglón en la tabla auditoria (acciones AUTORIZACION_CORRECCION y
-- REVOCACION_CORRECCION).
-- Por defecto es 0 (sin autorización). Se puede correr más de una vez sin efecto.
-- ============================================================

USE sgdm;

ALTER TABLE torneos
    ADD COLUMN IF NOT EXISTS correccion_autorizada TINYINT(1) NOT NULL DEFAULT 0 AFTER organizador_asignado_id;

ALTER TABLE torneos
    ADD CONSTRAINT IF NOT EXISTS chk_torneos_correccion_autorizada CHECK (correccion_autorizada IN (0, 1));
