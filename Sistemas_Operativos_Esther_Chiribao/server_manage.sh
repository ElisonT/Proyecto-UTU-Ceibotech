#!/bin/bash
# =========================================================================
# server_manage.sh - Script general de gestion del servidor SGDM
# Menu interactivo que centraliza las tareas administrativas: estado de
# servicios, respaldo de BD, monitoreo, antivirus, firewall y logs.
# Ejecutar como root: sudo ./server_manage.sh
# =========================================================================

SERVICIOS=("mysqld" "httpd" "firewalld" "clamav-freshclam")

mostrar_menu() {
    echo ""
    echo "========================================="
    echo "   GESTION DEL SERVIDOR - SGDM / PrimeCup"
    echo "========================================="
    echo "1) Ver estado de los servicios"
    echo "2) Iniciar/Reiniciar todos los servicios"
    echo "3) Ejecutar respaldo de base de datos"
    echo "4) Ejecutar monitoreo de base de datos"
    echo "5) Ejecutar escaneo de antivirus"
    echo "6) Ver estado del firewall"
    echo "7) Ver espacio en disco"
    echo "8) Ver ultimas lineas de logs del sistema"
    echo "9) Salir"
    echo "-----------------------------------------"
    read -p "Elegi una opcion: " OPCION
}

estado_servicios() {
    echo ""
    echo "--- Estado de servicios ---"
    for s in "${SERVICIOS[@]}"; do
        if systemctl is-active --quiet "$s"; then
            echo "[ACTIVO]   $s"
        else
            echo "[INACTIVO] $s"
        fi
    done
}

reiniciar_servicios() {
    echo ""
    echo "--- Reiniciando servicios ---"
    for s in "${SERVICIOS[@]}"; do
        echo "Reiniciando $s..."
        systemctl restart "$s" 2>/dev/null && echo "  OK: $s" || echo "  ERROR al reiniciar $s"
    done
}

respaldo_bd() {
    if [ -x /usr/local/bin/backup_mysql.sh ]; then
        /usr/local/bin/backup_mysql.sh
    else
        echo "No se encuentra /usr/local/bin/backup_mysql.sh"
    fi
}

monitoreo_bd() {
    if [ -x /usr/local/bin/db_monitor.sh ]; then
        /usr/local/bin/db_monitor.sh
    else
        echo "No se encuentra /usr/local/bin/db_monitor.sh (copialo ahi primero)"
    fi
}

escaneo_antivirus() {
    if [ -x /usr/local/bin/scan_antivirus.sh ]; then
        /usr/local/bin/scan_antivirus.sh
    else
        echo "No se encuentra /usr/local/bin/scan_antivirus.sh"
    fi
}

estado_firewall() {
    echo ""
    echo "--- Estado del firewall ---"
    firewall-cmd --state
    firewall-cmd --zone=public --list-all
}

espacio_disco() {
    echo ""
    echo "--- Espacio en disco ---"
    df -h
}

ver_logs() {
    echo ""
    echo "--- Ultimas 20 lineas de logs del sistema ---"
    journalctl -n 20 --no-pager
}

# --- Bucle principal del menu ---
while true; do
    mostrar_menu
    case $OPCION in
        1) estado_servicios ;;
        2) reiniciar_servicios ;;
        3) respaldo_bd ;;
        4) monitoreo_bd ;;
        5) escaneo_antivirus ;;
        6) estado_firewall ;;
        7) espacio_disco ;;
        8) ver_logs ;;
        9) echo "Saliendo..."; exit 0 ;;
        *) echo "Opcion invalida." ;;
    esac
done
