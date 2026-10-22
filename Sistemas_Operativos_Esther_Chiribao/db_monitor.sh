#!/bin/bash
# =========================================================================
# db_monitor.sh - Monitoreo y consultas de estado de la base de datos MySQL
# Revisa que el servicio este activo, el espacio ocupado, las conexiones
# actuales, y deja todo registrado en un log con fecha.
# Uso: sudo ./db_monitor.sh
# =========================================================================

# --- Credenciales de MySQL (usuario con permisos de monitoreo) ---
DB_USER="root"
DB_PASS="Parlante_121212"

FECHA=$(date +"%Y-%m-%d_%H-%M-%S")
LOG_DIR="/var/log/db_monitor"
LOG="$LOG_DIR/monitor_$FECHA.log"
mkdir -p "$LOG_DIR"

echo "=== Monitoreo de Base de Datos MySQL - $FECHA ===" | tee "$LOG"
echo "" | tee -a "$LOG"

# 1. Verificar que el servicio este activo
echo "--- Estado del servicio ---" | tee -a "$LOG"
if systemctl is-active --quiet mysqld; then
    echo "mysqld: ACTIVO" | tee -a "$LOG"
else
    echo "ALERTA: mysqld NO esta activo. Intentando reiniciar..." | tee -a "$LOG"
    systemctl restart mysqld
    sleep 3
    if systemctl is-active --quiet mysqld; then
        echo "mysqld reiniciado correctamente." | tee -a "$LOG"
    else
        echo "ERROR CRITICO: no se pudo reiniciar mysqld." | tee -a "$LOG"
    fi
fi
echo "" | tee -a "$LOG"

# 2. Version y uptime del servidor
echo "--- Version y tiempo activo ---" | tee -a "$LOG"
mysql -u "$DB_USER" -p"$DB_PASS" -e "STATUS;" 2>/dev/null | grep -E "Server version|Uptime" | tee -a "$LOG"
echo "" | tee -a "$LOG"

# 3. Conexiones activas en este momento
echo "--- Conexiones activas ---" | tee -a "$LOG"
mysql -u "$DB_USER" -p"$DB_PASS" -e "SHOW STATUS LIKE 'Threads_connected';" 2>/dev/null | tee -a "$LOG"
echo "" | tee -a "$LOG"

# 4. Listado de bases de datos y su tamaño en MB
echo "--- Tamaño de cada base de datos (MB) ---" | tee -a "$LOG"
mysql -u "$DB_USER" -p"$DB_PASS" -e "
SELECT table_schema AS 'Base de datos',
       ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Tamaño (MB)'
FROM information_schema.tables
GROUP BY table_schema;" 2>/dev/null | tee -a "$LOG"
echo "" | tee -a "$LOG"

# 5. Consultas lentas (slow queries) acumuladas
echo "--- Consultas lentas detectadas ---" | tee -a "$LOG"
mysql -u "$DB_USER" -p"$DB_PASS" -e "SHOW GLOBAL STATUS LIKE 'Slow_queries';" 2>/dev/null | tee -a "$LOG"
echo "" | tee -a "$LOG"

# 6. Espacio en disco del servidor (donde vive la BD)
echo "--- Espacio en disco ---" | tee -a "$LOG"
df -h / | tee -a "$LOG"
USO_DISCO=$(df / | tail -1 | awk '{print $5}' | tr -d '%')
if [ "$USO_DISCO" -ge 85 ]; then
    echo "ALERTA: el disco supera el 85% de uso ($USO_DISCO%)." | tee -a "$LOG"
fi
echo "" | tee -a "$LOG"

echo "=== Monitoreo finalizado. Log guardado en: $LOG ===" | tee -a "$LOG"
