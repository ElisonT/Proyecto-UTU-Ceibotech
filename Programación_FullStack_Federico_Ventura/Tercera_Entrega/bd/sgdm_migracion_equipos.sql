-- ============================================================
-- sgdm_migracion_equipos.sql
-- Se corre DESPUÉS de sgdm_migracion_inscripcion.sql.
--
-- Los equipos se arman AFUERA de los torneos (página "Mis equipos"):
--   * deportes: catálogo con el tamaño estándar de equipo de cada
--     deporte o juego (Fútbol 11 = 11, League of Legends = 5, Truco = 2,
--     Ajedrez = 1...). jugadores_por_equipo = 1 significa que se juega
--     de forma individual y no lleva equipos. De acá salen la modalidad
--     del torneo y el tamaño de cada equipo. La excepción es "Otro"
--     (es_libre = 1): ahí se escribe el nombre del juego y el tamaño.
--   * torneos.jugadores_por_equipo y equipos.deporte / jugadores_por_equipo:
--     copia del tamaño al crear el torneo o el equipo (del catálogo, o lo
--     que se escribió en "Otro"). Un equipo solo se inscribe a torneos del
--     mismo deporte y del mismo tamaño.
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
    es_libre             TINYINT(1)   NOT NULL DEFAULT 0,   -- 1 = "Otro": nombre y tamaño los escribe quien crea el torneo / equipo
    orden                INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT IGNORE INTO deportes (codigo, nombre, categoria, jugadores_por_equipo, orden) VALUES
    ('futbol11',     'Fútbol 11',         'Deportes tradicionales',   11,  1),
    ('futbol5',      'Fútbol 5',          'Deportes tradicionales',    5,  2),
    ('basquetbol',   'Básquetbol',        'Deportes tradicionales',    5,  3),
    ('tenis',        'Tenis',             'Deportes tradicionales',    1,  4),
    ('tenis_dobles', 'Tenis dobles',      'Deportes tradicionales',    2,  4),
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

-- Por si la tabla ya existía de una corrida anterior (sin la columna es_libre).
ALTER TABLE deportes
    ADD COLUMN IF NOT EXISTS es_libre TINYINT(1) NOT NULL DEFAULT 0 AFTER jugadores_por_equipo;
UPDATE deportes SET es_libre = 1 WHERE codigo = 'otro';

ALTER TABLE equipos
    ADD COLUMN IF NOT EXISTS capitan_id INT NULL AFTER nombre_equipo,
    ADD COLUMN IF NOT EXISTS deporte_id INT NULL AFTER capitan_id,
    ADD COLUMN IF NOT EXISTS deporte VARCHAR(60) NULL AFTER deporte_id,
    ADD COLUMN IF NOT EXISTS jugadores_por_equipo INT NULL AFTER deporte;

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

-- Copia del nombre y del tamaño del deporte para los equipos que todavía no la tienen.
UPDATE equipos eq
  JOIN deportes d ON d.id = eq.deporte_id
   SET eq.deporte = d.nombre,
       eq.jugadores_por_equipo = d.jugadores_por_equipo
 WHERE eq.deporte IS NULL;

ALTER TABLE equipos
    ADD CONSTRAINT fk_equipos_capitan FOREIGN KEY IF NOT EXISTS (capitan_id) REFERENCES usuarios(id);

ALTER TABLE equipos
    ADD CONSTRAINT fk_equipos_deporte FOREIGN KEY IF NOT EXISTS (deporte_id) REFERENCES deportes(id);

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
-- Torneos: jugadores_por_equipo (1 = individual). Para los que ya existían
-- sale del catálogo; después la modalidad se corrige solo en los torneos
-- que todavía no tienen ninguna inscripción, para no romper los que ya
-- tienen gente anotada.
-- ---------------------------------------------------------
ALTER TABLE torneos
    ADD COLUMN IF NOT EXISTS jugadores_por_equipo INT NULL AFTER cupos;

UPDATE torneos t
  JOIN deportes d ON d.nombre = t.deporte
   SET t.jugadores_por_equipo = d.jugadores_por_equipo
 WHERE t.jugadores_por_equipo IS NULL;

UPDATE torneos t
   SET t.modalidad = IF(COALESCE(t.jugadores_por_equipo, 1) >= 2, 'equipo', 'individual')
 WHERE NOT EXISTS (SELECT 1 FROM inscripciones i WHERE i.torneo_id = t.id);
