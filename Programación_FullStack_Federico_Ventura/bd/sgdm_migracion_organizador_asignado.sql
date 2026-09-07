-- ============================================================
-- sgdm_migracion_organizador_asignado.sql
-- Se corre DESPUÉS de sgdm_migracion_perfil_torneos.sql.
--
-- Corrige un error de permisos: según la letra (5.1 y 5.2), el
-- Administrador general CREA los torneos; al Organizador de
-- torneo se le ASIGNA un torneo ya creado para que lo gestione
-- (inscribir participantes, generar rondas, cargar resultados).
-- Son roles distintos y necesitan columnas distintas: hasta
-- ahora "creado_por" cumplía las dos funciones a la vez.
-- ============================================================

USE sgdm;

ALTER TABLE torneos
    ADD COLUMN IF NOT EXISTS organizador_asignado_id INT NULL AFTER creado_por,
    ADD CONSTRAINT fk_torneos_organizador
        FOREIGN KEY (organizador_asignado_id) REFERENCES usuarios(id);
