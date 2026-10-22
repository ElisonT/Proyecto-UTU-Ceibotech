-- ============================================================
-- sgdm_migracion_intentos_login.sql
-- Se corre DESPUÉS de sgdm_migracion_correccion_autorizada.sql.
--
-- Límite de intentos fallidos de inicio de sesión (fuerza bruta):
--   * 5 fallos para el mismo usuario/correo desde la misma IP en 15 minutos -> bloqueo de esa combinación
--   * 20 fallos desde la misma IP en 15 minutos (con cualquier usuario)       -> bloqueo de esa IP
-- Cada fallo es un renglón de esta tabla; al iniciar sesión bien se borran los de esa combinación,
-- y los de más de un día se limpian solos. Guarda el texto que se escribió en el campo de usuario
-- (en minúsculas y cortado a 100 caracteres), nunca la contraseña.
-- Se puede correr más de una vez sin efecto.
-- ============================================================

USE sgdm;

CREATE TABLE IF NOT EXISTS intentos_login (
    id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    login  VARCHAR(100)    NOT NULL,
    ip     VARCHAR(45)     NOT NULL,
    fecha  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_intentos_login_ip   (login, ip, fecha),
    KEY idx_intentos_ip_fecha   (ip, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
