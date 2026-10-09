-- ============================================================
-- sgdm_migracion_competencia.sql
-- Se corre DESPUÉS de todas las migraciones anteriores.
--
-- Implementa el núcleo de la gestión real de torneos, según el
-- DER de la entrega final: equipos, inscripciones (con soporte
-- para deportes de equipo Y deportes individuales), rondas,
-- enfrentamientos, resultados y tabla de posiciones.
--
-- Decisión de diseño clave (tomada del DER, no inventada acá):
-- en vez de forzar todo a "equipo" (y tratar un individual como
-- un equipo de una sola persona), existe el concepto de
-- "INSCRIPTO": lo que se anota a un torneo puede ser un EQUIPO
-- o un PARTICIPANTE individual, nunca los dos a la vez. Esto se
-- implementa como un "arco exclusivo" en la tabla inscripciones:
-- se completa equipo_id O usuario_id, nunca ambos ni ninguno.
-- ============================================================

USE sgdm;

-- ---------------------------------------------------------
-- TORNEO.modalidad: define si ese torneo se juega por equipos
-- o de forma individual. De esto depende cuál de las dos
-- columnas de inscripciones se usa para ese torneo.
-- ---------------------------------------------------------
ALTER TABLE torneos
    ADD COLUMN IF NOT EXISTS modalidad ENUM('individual', 'equipo') NOT NULL DEFAULT 'equipo' AFTER formato;

-- ---------------------------------------------------------
-- Tabla: equipos
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS equipos (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    nombre_equipo            VARCHAR(100) NOT NULL,
    logo                     VARCHAR(255) NULL,
    perfil_publico_habilitado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabla: equipo_miembros (relación INTEGRA: N usuarios <-> M equipos)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS equipo_miembros (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id      INT NOT NULL,
    usuario_id     INT NOT NULL,
    fecha_ingreso  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_equipo_miembros_equipo  FOREIGN KEY (equipo_id)  REFERENCES equipos(id),
    CONSTRAINT fk_equipo_miembros_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    UNIQUE KEY uq_equipo_usuario (equipo_id, usuario_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabla: inscripciones (se amplía la versión anterior, más
-- simple, con el arco exclusivo equipo/usuario). Las
-- inscripciones de prueba que ya había eran todas de
-- participante individual (usuario_id), así que se conservan.
-- ---------------------------------------------------------
ALTER TABLE inscripciones
    ADD COLUMN IF NOT EXISTS equipo_id INT NULL AFTER torneo_id,
    ADD COLUMN IF NOT EXISTS alias VARCHAR(60) NULL AFTER usuario_id,
    ADD COLUMN IF NOT EXISTS estado_inscripcion ENUM('activa', 'retirada', 'descalificada') NOT NULL DEFAULT 'activa' AFTER alias,
    MODIFY COLUMN usuario_id INT NULL;

-- La UNIQUE KEY original (torneo_id, usuario_id) no sirve más tal
-- cual (usuario_id ahora puede ser NULL en muchas filas a la vez).
-- Antes de borrarla hay que darle a fk_inscripciones_torneo un
-- índice de respaldo propio, porque hoy se apoya en esta misma
-- unique key (torneo_id es la primera columna) y MySQL no deja
-- borrar un índice del que depende una FK sin reemplazo.
ALTER TABLE inscripciones ADD INDEX IF NOT EXISTS idx_inscripciones_torneo (torneo_id);
ALTER TABLE inscripciones DROP INDEX IF EXISTS uq_torneo_usuario;

ALTER TABLE inscripciones
    ADD CONSTRAINT fk_inscripciones_equipo FOREIGN KEY (equipo_id) REFERENCES equipos(id);

-- Arco exclusivo: exactamente uno de los dos debe estar completo.
-- (Requiere MariaDB 10.2+ / MySQL 8.0.16+, que ya cumplimos.)
ALTER TABLE inscripciones
    ADD CONSTRAINT chk_inscripciones_arco_exclusivo
        CHECK (
            (usuario_id IS NOT NULL AND equipo_id IS NULL)
            OR
            (usuario_id IS NULL AND equipo_id IS NOT NULL)
        );

-- ---------------------------------------------------------
-- Tabla: rondas
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS rondas (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    torneo_id  INT NOT NULL,
    numero     INT NOT NULL,
    nombre     VARCHAR(60) NULL,   -- ej: "Fecha 3", "Cuartos de final"
    fecha      DATE NULL,

    CONSTRAINT fk_rondas_torneo FOREIGN KEY (torneo_id) REFERENCES torneos(id),
    UNIQUE KEY uq_torneo_numero_ronda (torneo_id, numero)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabla: enfrentamientos
-- Un enfrentamiento siempre es 1 a 1 (local vs. visitante), sea
-- cual sea la modalidad del torneo: ambos lados son una fila de
-- "inscripciones" (que a su vez puede ser un equipo o un
-- participante individual, según el arco exclusivo de arriba).
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS enfrentamientos (
    id                        INT AUTO_INCREMENT PRIMARY KEY,
    ronda_id                  INT NOT NULL,
    inscripcion_local_id      INT NOT NULL,
    inscripcion_visitante_id  INT NOT NULL,
    fecha_hora                DATETIME NULL,
    estado                    ENUM('pendiente', 'jugado', 'cancelado') NOT NULL DEFAULT 'pendiente',

    CONSTRAINT fk_enfrentamientos_ronda      FOREIGN KEY (ronda_id)               REFERENCES rondas(id),
    CONSTRAINT fk_enfrentamientos_local      FOREIGN KEY (inscripcion_local_id)     REFERENCES inscripciones(id),
    CONSTRAINT fk_enfrentamientos_visitante  FOREIGN KEY (inscripcion_visitante_id) REFERENCES inscripciones(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabla: resultados (relación GENERA: 1 a 1 con enfrentamiento)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS resultados (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    enfrentamiento_id       INT NOT NULL,
    puntaje_local           INT NOT NULL DEFAULT 0,
    puntaje_visitante       INT NOT NULL DEFAULT 0,
    inscripcion_ganadora_id INT NULL,   -- NULL = empate
    validado                TINYINT(1) NOT NULL DEFAULT 0,
    fecha_carga             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_resultados_enfrentamiento FOREIGN KEY (enfrentamiento_id)       REFERENCES enfrentamientos(id),
    CONSTRAINT fk_resultados_ganador        FOREIGN KEY (inscripcion_ganadora_id) REFERENCES inscripciones(id),
    UNIQUE KEY uq_resultado_por_enfrentamiento (enfrentamiento_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabla: tabla_posiciones (relación TIENE_POSICION: 1 a 1 con
-- inscripcion). Se guarda como tabla propia -tal como está en
-- el DER- y se recalcula después de cada resultado validado,
-- en vez de calcularse al vuelo en cada consulta.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS tabla_posiciones (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    inscripcion_id       INT NOT NULL,
    posicion             INT NULL,
    puntos               INT NOT NULL DEFAULT 0,
    partidos_jugados     INT NOT NULL DEFAULT 0,
    partidos_ganados     INT NOT NULL DEFAULT 0,
    partidos_empatados   INT NOT NULL DEFAULT 0,
    partidos_perdidos    INT NOT NULL DEFAULT 0,

    CONSTRAINT fk_tabla_posiciones_inscripcion FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id),
    UNIQUE KEY uq_posicion_por_inscripcion (inscripcion_id)
) ENGINE=InnoDB;
