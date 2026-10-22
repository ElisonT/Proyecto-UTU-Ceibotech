#!/bin/bash
# =========================================================================
# setup_antivirus.sh - Instalacion y configuracion de ClamAV en AlmaLinux
# ClamAV es el antivirus estandar en entornos Linux/servidor (open source).
# Instala el motor, actualiza las firmas de virus y deja un escaneo
# diario programado por cron, con log de resultados.
# Ejecutar como root: sudo ./setup_antivirus.sh
# =========================================================================

set -e

echo "=== Instalando ClamAV ==="

# 1. Habilitar el repositorio EPEL (ClamAV no esta en los repos base de AlmaLinux)
if ! rpm -q epel-release &>/dev/null; then
    echo "Instalando repositorio EPEL..."
    dnf install -y epel-release
fi

# 2. Instalar ClamAV, el demonio y las herramientas de actualizacion de firmas
echo "Instalando paquetes clamav..."
dnf install -y clamav clamav-update clamd

# 3. Configurar freshclam (actualizador de firmas de virus)
echo "Configurando actualizador de firmas (freshclam)..."
sed -i 's/^Example/#Example/' /etc/freshclam.conf 2>/dev/null || true

# 4. Primera actualizacion de firmas (puede tardar unos minutos)
echo "Descargando firmas de virus actualizadas..."
freshclam || echo "Aviso: freshclam fallo en la primera corrida, revisar conexion a internet"

# 5. Habilitar actualizacion automatica periodica de firmas
systemctl enable --now clamav-freshclam 2>/dev/null || echo "Servicio clamav-freshclam no disponible, las firmas se actualizaran manualmente via cron"

# 6. Carpeta para los logs de escaneo
mkdir -p /var/log/clamav_scans

# 7. Crear script de escaneo diario
cat > /usr/local/bin/scan_antivirus.sh << 'EOF'
#!/bin/bash
# scan_antivirus.sh - Escaneo diario del servidor con ClamAV
FECHA=$(date +"%Y-%m-%d_%H-%M-%S")
LOG="/var/log/clamav_scans/scan_$FECHA.log"

echo "Iniciando escaneo: $FECHA" > "$LOG"

# Escanea directorios criticos del servidor (web, home, tmp)
# --infected: solo reporta archivos infectados
# --remove: elimina automaticamente los archivos infectados detectados
clamscan -r --infected --remove \
    /var/www \
    /home \
    /tmp \
    >> "$LOG" 2>&1

if [ $? -eq 0 ]; then
    echo "Escaneo finalizado sin infecciones: $LOG" >> "$LOG"
else
    echo "ATENCION: se detectaron y eliminaron archivos infectados. Ver $LOG" >> "$LOG"
fi

echo "Resultado guardado en: $LOG"
EOF

chmod +x /usr/local/bin/scan_antivirus.sh

# 8. Programar el escaneo diario via cron (todos los dias a las 3:00 AM)
(crontab -l 2>/dev/null | grep -v scan_antivirus.sh; echo "0 3 * * * /usr/local/bin/scan_antivirus.sh") | crontab -

echo ""
echo "=== ClamAV instalado y configurado ==="
echo "Escaneo programado: todos los dias a las 3:00 AM"
echo "Logs de escaneo en: /var/log/clamav_scans/"
echo "Para escanear manualmente ahora: sudo /usr/local/bin/scan_antivirus.sh"
