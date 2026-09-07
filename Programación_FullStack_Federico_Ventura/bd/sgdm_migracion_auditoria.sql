-- ============================================================
-- sgdm_migracion_auditoria.sql
-- Se corre DESPUÉS de las migraciones anteriores.
--
-- La letra pide (sección 4.1 y sección 14) que quede registrada
-- una historia de todo lo que se modifica en la base de datos.
-- Esta tabla cumple esa función: cada acción relevante (alta de
-- usuario, login, edición de perfil, cambio de contraseña,
-- eliminación de cuenta, creación de torneo, asignación de
-- organizador) queda registrada acá, con quién la hizo y cuándo.
-- ============================================================

USE sgdm;

CREATE TABLE IF NOT EXISTS auditoria (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT NULL,              -- quién hizo la acción (NULL si no aplica)
    accion          VARCHAR(50) NOT NULL,  -- ej: 'ALTA_USUARIO', 'LOGIN', 'CREACION_TORNEO'
    tabla_afectada  VARCHAR(50) NULL,      -- ej: 'usuarios', 'torneos'
    registro_id     INT NULL,              -- id de la fila afectada en esa tabla
    detalle         VARCHAR(255) NULL,     -- descripción legible de qué pasó
    ip              VARCHAR(45) NULL,      -- dirección IP de quien hizo la acción
    fecha           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- ON DELETE SET NULL: si la cuenta del usuario se anonimiza más adelante
    -- (ver Usuario::anonimizarCuenta), el historial de auditoría no se toca;
    -- solo perdería la referencia si la fila de usuarios se borrara de verdad,
    -- cosa que ya no hacemos.
    CONSTRAINT fk_auditoria_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_auditoria_usuario ON auditoria(usuario_id);
CREATE INDEX idx_auditoria_fecha   ON auditoria(fecha);
