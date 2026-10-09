-- ============================================================
-- sgdm_migracion_ganador.sql
-- Se corre DESPUÉS de sgdm_migracion_bye.sql.
--
-- torneos.ganador_id apuntaba directo a usuarios(id), pero eso
-- solo tiene sentido para torneos individuales. Si el torneo es
-- por equipos (modalidad='equipo'), el que gana es un EQUIPO, no
-- un usuario. Se corrige para que apunte a inscripciones(id), que
-- ya resuelve correctamente uno u otro caso (arco exclusivo).
-- ============================================================

USE sgdm;

ALTER TABLE torneos DROP FOREIGN KEY fk_torneos_ganador;
ALTER TABLE torneos DROP COLUMN ganador_id;
ALTER TABLE torneos ADD COLUMN ganador_inscripcion_id INT NULL AFTER organizador_asignado_id;
ALTER TABLE torneos
    ADD CONSTRAINT fk_torneos_ganador_inscripcion
        FOREIGN KEY (ganador_inscripcion_id) REFERENCES inscripciones(id);
