#!/bin/bash
# docker/db/init.sh
#
# La imagen de MariaDB ejecuta este script UNA sola vez, la primera vez que arranca con el
# volumen vacío (después de crear la base "sgdm" y el usuario de la aplicación). Carga el esquema,
# todas las migraciones EN ORDEN y, si se pide, los datos de prueba.
#
# Está escrito para funcionar tanto si el archivo es ejecutable como si la imagen lo "lee"
# (en Windows el bit de ejecución se pierde): por eso va todo dentro de una función.

cargar_sgdm() {
    local cliente=(mariadb -uroot "-p${MARIADB_ROOT_PASSWORD}" --default-character-set=utf8mb4)

    # El orden es el de las notas "se corre DESPUÉS de..." de cada archivo.
    # sgdm_dcl.sql no se usa acá: en Docker el usuario lo crea MARIADB_USER.
    local archivos=(
        sgdm_schema.sql
        sgdm_migracion_perfil_torneos.sql
        sgdm_migracion_organizador_asignado.sql
        sgdm_migracion_auditoria.sql
        sgdm_migracion_competencia.sql
        sgdm_migracion_bye.sql
        sgdm_migracion_ganador.sql
        sgdm_migracion_inscripcion.sql
        sgdm_migracion_equipos.sql
        sgdm_migracion_fechas.sql
        sgdm_migracion_correccion_vistas.sql
    )

    local archivo
    for archivo in "${archivos[@]}"; do
        echo "[sgdm] Ejecutando ${archivo}"
        "${cliente[@]}" < "/sql/${archivo}" || { echo "[sgdm] ERROR en ${archivo}"; return 1; }
    done

    # Mismo criterio que bd/sgdm_dcl.sql: la aplicación solo puede leer y escribir datos,
    # nunca crear ni borrar tablas (principio de menor privilegio).
    echo "[sgdm] Permisos mínimos para ${MARIADB_USER}"
    "${cliente[@]}" -e "REVOKE ALL PRIVILEGES ON sgdm.* FROM '${MARIADB_USER}'@'%';
                        GRANT SELECT, INSERT, UPDATE, DELETE ON sgdm.* TO '${MARIADB_USER}'@'%';
                        FLUSH PRIVILEGES;" || return 1

    if [ "${CARGAR_DATOS_PRUEBA:-1}" = "1" ]; then
        echo "[sgdm] Cargando datos de prueba"
        "${cliente[@]}" < /sql/sgdm_datos_prueba.sql || { echo "[sgdm] ERROR en los datos de prueba"; return 1; }
        # Corre las fechas del seed al día de hoy (si no, las inscripciones "abiertas" ya vencieron).
        "${cliente[@]}" < /sql/sgdm_datos_prueba_actualizar_fechas.sql || { echo "[sgdm] ERROR al actualizar fechas"; return 1; }
    else
        echo "[sgdm] Datos de prueba omitidos (CARGAR_DATOS_PRUEBA=0)"
    fi

    echo "[sgdm] Base de datos lista"
}

cargar_sgdm
