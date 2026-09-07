-- ============================================================
-- sgdm_migracion_perfil_torneos.sql
-- Se corre DESPUÉS de sgdm_schema.sql (no lo reemplaza).
-- Agrega:
--   1) La columna "foto" a usuarios (guarda la RUTA del archivo,
--      no la imagen en sí — la imagen vive en uploads/avatars/).
--   2) Las tablas mínimas para que "Torneos jugados/activos/
--      ganados" en el perfil muestren datos reales.
-- El usuario sgdm_app ya tiene permisos sobre sgdm.* completo
-- (ver sgdm_dcl.sql), así que no hace falta volver a otorgar nada.
-- ============================================================

USE sgdm;

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS foto VARCHAR(255) NULL AFTER genero;

-- ---------------------------------------------------------
-- Tabla: torneos
-- Todavía mínima a propósito: en la próxima entrega se le van
-- a sumar equipos, rondas, enfrentamientos y resultados. Por
-- ahora alcanza para mostrar listas y contadores reales.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS torneos (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(120) NOT NULL,
    deporte        VARCHAR(60)  NOT NULL,
    formato        ENUM('liga', 'eliminacion', 'suizo') NOT NULL,
    estado         ENUM('inscripciones_abiertas', 'en_curso', 'finalizado')
                       NOT NULL DEFAULT 'inscripciones_abiertas',
    fecha_inicio   DATE NULL,
    creado_por     INT NOT NULL,
    ganador_id     INT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_torneos_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id),
    CONSTRAINT fk_torneos_ganador FOREIGN KEY (ganador_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabla: inscripciones
-- Relación N:M entre usuarios y torneos (un participante puede
-- estar anotado en varios torneos, y un torneo tiene varios
-- participantes). UNIQUE evita anotarse dos veces al mismo torneo.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS inscripciones (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    torneo_id          INT NOT NULL,
    usuario_id         INT NOT NULL,
    fecha_inscripcion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_inscripciones_torneo  FOREIGN KEY (torneo_id)  REFERENCES torneos(id),
    CONSTRAINT fk_inscripciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    UNIQUE KEY uq_torneo_usuario (torneo_id, usuario_id)
) ENGINE=InnoDB;
