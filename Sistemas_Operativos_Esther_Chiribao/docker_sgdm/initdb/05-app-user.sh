#!/bin/bash
# Crea el usuario de la app con minimo privilegio (equivale al sgdm_dcl.sql
# del equipo, pero con host '%' porque la web se conecta desde OTRO
# contenedor y no desde 'localhost'). La contrasena viene del .env.
set -e
mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" <<SQL
CREATE USER IF NOT EXISTS 'sgdm_app'@'%' IDENTIFIED BY '${DB_APP_PASSWORD}';
GRANT SELECT, INSERT, UPDATE, DELETE ON sgdm.* TO 'sgdm_app'@'%'
    WITH MAX_QUERIES_PER_HOUR 10000 MAX_USER_CONNECTIONS 20;
FLUSH PRIVILEGES;
SQL
