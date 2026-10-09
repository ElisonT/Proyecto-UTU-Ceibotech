-- ============================================================
-- sgdm_migracion_equipos.sql
-- Se corre DESPUÉS de sgdm_migracion_inscripcion.sql.
--
-- Los equipos se arman AFUERA de los torneos (página "Mis equipos"):
--   * deportes: catálogo con el tamaño estándar de equipo de cada
--     deporte o juego (Fútbol 11 = 11, League of Legends = 5, Truco = 2,
--     Ajedrez = 1...). jugadores_por_equipo = 1 significa que se juega
--     de forma individual y no lleva equipos. De acá salen la modalidad
--     del torneo y el tamaño de cada equipo: nadie lo elige a mano.
--   * equipos.deporte_id: cada equipo se crea PARA un deporte y solo
--     puede inscribirse a torneos de ese deporte.
--   * equipos.capitan_id: quien creó el equipo. Solo el capitán
--     invita, saca integrantes, disuelve el equipo e inscribe al
--     equipo a un torneo.
--   * invitaciones_equipo: el capitán invita a un usuario y el
--     invitado tiene que aceptar (o rechazar). Al aceptar recién
--     entra a equipo_miembros.
-- ============================================================

USE sgdm;

-- ---------------------------------------------------------
-- Tabla: deportes (catálogo). Para agregar un juego nuevo alcanza con
-- insertar una fila; no hay que tocar el código.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS deportes (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    codigo               VARCHAR(30)  NOT NULL UNIQUE,   -- lo que manda el <select> de crear-torneo.php
    nombre               VARCHAR(60)  NOT NULL UNIQUE,   -- es lo que se guarda en torneos.deporte
    categoria            VARCHAR(40)  NOT NULL,
    jugadores_por_equipo INT          NOT NULL DEFAULT 1,
    orden                INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT IGNORE INTO deportes (codigo, nombre, categoria, jugadores_por_equipo, orden) VALUES
    ('futbol11',     'Fútbol 11',         'Deportes tradicionales',   11,  1),
    ('futbol5',      'Fútbol 5',          'Deportes tradicionales',    5,  2),
    ('basquetbol',   'Básquetbol',        'Deportes tradicionales',    5,  3),
    ('tenis',        'Tenis',             'Deportes tradicionales',    1,  4),
    ('padel',        'Pádel',             'Deportes tradicionales',    2,  5),
    ('pingpong',     'Ping pong',         'Deportes tradicionales',    1,  6),
    ('ajedrez',      'Ajedrez',           'Juegos de mesa / carta',    1,  7),
    ('truco',        'Truco',             'Juegos de mesa / carta',    2,  8),
    ('damas',        'Damas',             'Juegos de mesa / carta',    1,  9),
    ('cs2',          'CS2',               'Videojuegos',               5, 10),
    ('lol',          'League of Legends', 'Videojuegos',               5, 11),
    ('fortnite',     'Fortnite',          'Videojuegos',               1, 12),
    ('valorant',     'Valorant',          'Videojuegos',               5, 13),
    ('rocketleague', 'Rocket League',     'Videojuegos',               3, 14),
    ('otro',         'Otro',              'Otro',                      1, 15);

ALTER TABLE equipos
    ADD COLUMN IF NOT EXISTS capitan_id INT NULL AFTER nombre_equipo,
    ADD COLUMN IF NOT EXISTS deporte_id INT NULL AFTER capitan_id;

-- Equipos que ya existían (creados con la inscripción anterior): el
-- capitán es el integrante más antiguo, o sea quien lo creó.
UPDATE equipos eq
   SET eq.capitan_id = (
        SELECT em.usuario_id
          FROM equipo_miembros em
         WHERE em.equipo_id = eq.id
         ORDER BY em.fecha_ingreso ASC, em.id ASC
         LIMIT 1
   )
 WHERE eq.capitan_id IS NULL;

-- Equipos que ya existían: el deporte es el del torneo al que se inscribieron.
UPDATE equipos eq
   SET eq.deporte_id = (
        SELECT d.id
          FROM inscripciones i
          JOIN torneos t  ON t.id = i.torneo_id
          JOIN deportes d ON d.nombre = t.deporte
         WHERE i.equipo_id = eq.id
         ORDER BY i.id ASC
         LIMIT 1
   )
 WHERE eq.deporte_id IS NULL;

ALTER TABLE equipos
    ADD CONSTRAINT IF NOT EXISTS fk_equipos_capitan FOREIGN KEY (capitan_id) REFERENCES usuarios(id);

ALTER TABLE equipos
    ADD CONSTRAINT IF NOT EXISTS fk_equipos_deporte FOREIGN KEY (deporte_id) REFERENCES deportes(id);

CREATE TABLE IF NOT EXISTS invitaciones_equipo (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id        INT NOT NULL,
    usuario_id       INT NOT NULL,            -- el invitado
    invitado_por     INT NOT NULL,            -- el capitán que invitó
    estado           ENUM('pendiente', 'aceptada', 'rechazada', 'cancelada') NOT NULL DEFAULT 'pendiente',
    fecha_creacion   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_respuesta  DATETIME NULL,

    CONSTRAINT fk_invitaciones_equipo   FOREIGN KEY (equipo_id)    REFERENCES equipos(id),
    CONSTRAINT fk_invitaciones_usuario  FOREIGN KEY (usuario_id)   REFERENCES usuarios(id),
    CONSTRAINT fk_invitaciones_capitan  FOREIGN KEY (invitado_por) REFERENCES usuarios(id),
    INDEX idx_invitaciones_usuario (usuario_id, estado),
    INDEX idx_invitaciones_equipo  (equipo_id, estado)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Torneos que ya existían: la modalidad pasa a depender del deporte
-- (antes quedaba en 'equipo' por defecto aunque fuera Ajedrez). Solo se
-- corrige en torneos que todavía no tienen ninguna inscripción, para no
-- romper los que ya tienen gente anotada.
-- ---------------------------------------------------------
UPDATE torneos t
  JOIN deportes d ON d.nombre = t.deporte
   SET t.modalidad = IF(d.jugadores_por_equipo >= 2, 'equipo', 'individual')
 WHERE NOT EXISTS (SELECT 1 FROM inscripciones i WHERE i.torneo_id = t.id);
