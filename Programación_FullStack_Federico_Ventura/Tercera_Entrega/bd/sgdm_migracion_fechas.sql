-- ============================================================
-- sgdm_migracion_fechas.sql
-- Se corre DESPUÉS de sgdm_migracion_equipos.sql.
--
-- Guarda las fechas que pide el formulario de crear torneo y que hasta
-- ahora se perdían:
--   * fecha_apertura_inscripcion / fecha_cierre_inscripcion: ventana en
--     la que se puede inscribir (o darse de baja con el torneo abierto).
--     Fuera de esas fechas la aplicación rechaza la inscripción.
--     NULL = sin límite de fechas (los torneos que ya existían quedan así).
--   * fecha_fin: fin previsto del torneo (informativa).
-- ============================================================

USE sgdm;

ALTER TABLE torneos
    ADD COLUMN IF NOT EXISTS fecha_apertura_inscripcion DATE NULL AFTER fecha_inicio,
    ADD COLUMN IF NOT EXISTS fecha_cierre_inscripcion   DATE NULL AFTER fecha_apertura_inscripcion,
    ADD COLUMN IF NOT EXISTS fecha_fin                  DATE NULL AFTER fecha_cierre_inscripcion;
