-- ============================================================
-- sgdm_dcl.sql
-- Control de acceso a la base de datos (DCL).
-- La aplicación web NUNCA se conecta como root: usa un usuario
-- de MySQL aparte, con solo los permisos que necesita para
-- funcionar (principio de menor privilegio). Esto también se
-- documenta en la parte de Ciberseguridad de la letra.
-- ============================================================

-- Cambiá 'CAMBIAR_ESTA_CONTRASEÑA' por una contraseña real y
-- fuerte antes de ejecutar esto en el servidor. Esa misma
-- contraseña va después en config/database.php.
CREATE USER IF NOT EXISTS 'sgdm_app'@'localhost'
    IDENTIFIED BY 'CAMBIAR_ESTA_CONTRASEÑA';

-- Solo lectura/escritura de datos (CRUD). Nada de DROP, ALTER,
-- CREATE ni GRANT: si el sitio tiene una falla de seguridad,
-- el usuario de la app no puede borrar tablas ni escalar permisos.
-- Además, se le pone un techo de uso (consultas por hora y
-- conexiones simultáneas), para que un bug o un ataque no pueda
-- dejar la base sin recursos abriendo conexiones sin parar.
GRANT SELECT, INSERT, UPDATE, DELETE ON sgdm.* TO 'sgdm_app'@'localhost'
    WITH MAX_QUERIES_PER_HOUR 10000
         MAX_USER_CONNECTIONS 20;

-- ------------------------------------------------------------
-- Usuario aparte, de SOLO LECTURA, para los respaldos (mysqldump).
-- El script de backups (Administración de Sistemas Operativos)
-- no necesita la contraseña de la app: usa este usuario, que ni
-- siquiera puede escribir, solo leer y exportar.
-- ------------------------------------------------------------
CREATE USER IF NOT EXISTS 'sgdm_backup'@'localhost'
    IDENTIFIED BY 'CAMBIAR_ESTA_OTRA_CONTRASEÑA';

GRANT SELECT, LOCK TABLES, SHOW VIEW ON sgdm.* TO 'sgdm_backup'@'localhost';

FLUSH PRIVILEGES;

-- ------------------------------------------------------------
-- Verificación: confirma exactamente qué permisos quedaron
-- otorgados a cada usuario. Sirve como evidencia para la
-- documentación de seguridad (una captura de esto alcanza).
-- ------------------------------------------------------------
SHOW GRANTS FOR 'sgdm_app'@'localhost';
SHOW GRANTS FOR 'sgdm_backup'@'localhost';
