-- ============================================================
-- sgdm_schema.sql
-- Modelo relacional mínimo para que funcione el registro/login.
-- Se irá ampliando en las próximas entregas con torneos,
-- participantes, equipos, enfrentamientos, resultados, etc.
-- (ver el listado completo de entidades en la letra del proyecto).
-- ============================================================

CREATE DATABASE IF NOT EXISTS sgdm
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sgdm;

-- ---------------------------------------------------------
-- Tabla: roles
-- Roles del sistema descritos en la letra (punto 5):
-- Administrador general, Organizador de torneo, Participante.
-- (El "Usuario público" no necesita fila, porque no se autentica).
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO roles (nombre) VALUES
    ('Administrador general'),
    ('Organizador de torneo'),
    ('Participante')
ON DUPLICATE KEY UPDATE nombre = nombre;

-- ---------------------------------------------------------
-- Tabla: usuarios
-- nombre_usuario y email son UNIQUE porque el login acepta
-- indistintamente uno u otro (ver login.html, campo "login").
-- rol_id referencia a roles: por defecto, todo el que se
-- autoregistra desde register.html queda como "Participante".
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario   VARCHAR(30)  NOT NULL UNIQUE,
    nombre_completo  VARCHAR(100) NOT NULL,
    email            VARCHAR(150) NOT NULL UNIQUE,
    celular          VARCHAR(20)  NULL,
    contrasena_hash  VARCHAR(255) NOT NULL,
    genero           VARCHAR(20)  NULL,
    rol_id           INT NOT NULL,
    activo           TINYINT(1)   NOT NULL DEFAULT 1,
    fecha_registro   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_usuarios_rol
        FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;
