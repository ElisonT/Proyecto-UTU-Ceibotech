-- ============================================================
-- sgdm_migracion_inscripcion.sql
-- Se corre DESPUÉS de sgdm_migracion_ganador.sql.
--
-- Prepara la inscripción real a torneos:
--   * torneos.cupos: máximo de inscriptos (individuales o equipos).
--     NULL = sin límite (los torneos viejos quedan así).
--     (El tamaño de los equipos NO se guarda acá: lo define el
--     deporte, ver sgdm_migracion_equipos.sql -> tabla deportes.)
--   * Se vuelven a poner las UNIQUE de inscripciones, ahora
--     compatibles con el arco exclusivo: MySQL permite varios NULL
--     en una UNIQUE, así que (torneo_id, usuario_id) no molesta a
--     las inscripciones de equipo (usuario_id NULL) y viceversa.
-- ============================================================

USE sgdm;

ALTER TABLE torneos
    ADD COLUMN IF NOT EXISTS cupos INT NULL AFTER modalidad;

ALTER TABLE inscripciones
    ADD UNIQUE INDEX IF NOT EXISTS uq_torneo_usuario (torneo_id, usuario_id);

ALTER TABLE inscripciones
    ADD UNIQUE INDEX IF NOT EXISTS uq_torneo_equipo (torneo_id, equipo_id);
