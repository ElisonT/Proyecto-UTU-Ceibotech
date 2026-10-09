-- ============================================================
-- sgdm_migracion_bye.sql
-- Se corre DESPUÉS de sgdm_migracion_competencia.sql.
--
-- En eliminación directa, si la cantidad de inscriptos no es
-- potencia de 2 (4, 8, 16...), alguien tiene que pasar de ronda
-- sin jugar ("bye" / descanso) en la primera ronda. Se representa
-- como un enfrentamiento sin visitante: inscripcion_visitante_id
-- queda NULL, y el local gana automáticamente.
-- ============================================================

USE sgdm;

ALTER TABLE enfrentamientos
    MODIFY COLUMN inscripcion_visitante_id INT NULL;
