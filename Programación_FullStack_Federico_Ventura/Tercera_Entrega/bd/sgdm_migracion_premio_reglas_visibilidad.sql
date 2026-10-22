-- ============================================================
-- sgdm_migracion_premio_reglas_visibilidad.sql
-- Se corre DESPUÉS de sgdm_migracion_editar_cancelar.sql.
--
-- Los pasos "Premio", "Reglas" y "Visibilidad" del asistente de crear torneo ahora se guardan:
--   * tipo_premio / monto_premio (USD) / desc_premio   -> premio del torneo (ninguno, monetario u otro)
--   * reglas_texto                                      -> reglas redactadas a mano
--   * torneo_reglas_pdf                                 -> reglas en PDF (hasta 2 MB); si hay PDF se usa ese
--   * visibilidad                                       -> 'publico' (aparece en portada y búsquedas)
--                                                          'privado' (solo se llega con el link directo)
-- El PDF se guarda en la base (y no en una carpeta del servidor) para que viaje con los backups y no
-- dependa de permisos de carpetas ni de volúmenes. Está en una tabla aparte para que las consultas
-- comunes de torneos no lo arrastren. Se puede correr más de una vez sin efecto.
-- ============================================================

USE sgdm;

ALTER TABLE torneos
    ADD COLUMN IF NOT EXISTS tipo_premio  ENUM('ninguno', 'monetario', 'otro') NOT NULL DEFAULT 'ninguno',
    ADD COLUMN IF NOT EXISTS monto_premio DECIMAL(10,2) NULL,
    ADD COLUMN IF NOT EXISTS desc_premio  VARCHAR(300)  NULL,
    ADD COLUMN IF NOT EXISTS reglas_texto TEXT          NULL,
    ADD COLUMN IF NOT EXISTS visibilidad  ENUM('publico', 'privado') NOT NULL DEFAULT 'publico';

CREATE TABLE IF NOT EXISTS torneo_reglas_pdf (
    torneo_id      INT          NOT NULL,
    nombre_archivo VARCHAR(150) NOT NULL,
    tamano         INT UNSIGNED NOT NULL,
    contenido      MEDIUMBLOB   NOT NULL,
    fecha_subida   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (torneo_id),
    CONSTRAINT fk_reglas_pdf_torneo FOREIGN KEY (torneo_id) REFERENCES torneos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
