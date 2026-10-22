#!/bin/bash
# =========================================================================
# configure_firewall.sh - Configuracion de firewall para el servidor SGDM
# AlmaLinux usa firewalld como interfaz de gestion sobre nftables/iptables
# por defecto. Este script lo configura para dejar pasar solo lo necesario.
# Ejecutar como root: sudo ./configure_firewall.sh
# =========================================================================

set -e

echo "=== Configurando firewall (firewalld) ==="

# 1. Instalar y habilitar firewalld si no esta activo
if ! systemctl is-active --quiet firewalld; then
    echo "Instalando/activando firewalld..."
    dnf install -y firewalld
    systemctl enable --now firewalld
fi

# 2. Zona de trabajo: 'public' es la zona por defecto en AlmaLinux
ZONA="public"

# 3. Servicios permitidos (reglas por servicio, mas legible que puertos sueltos)
echo "Habilitando servicios esenciales..."
firewall-cmd --zone=$ZONA --add-service=ssh --permanent
firewall-cmd --zone=$ZONA --add-service=http --permanent
firewall-cmd --zone=$ZONA --add-service=https --permanent

# 4. MySQL (puerto 3306) SOLO accesible desde localhost -- nunca se expone
#    el puerto de la base de datos a internet. Si necesitas administrarla
#    remotamente, usa un tunel SSH en vez de abrir el puerto.
#    (No se agrega ninguna regla para 3306: queda bloqueado por defecto)
echo "Puerto 3306 (MySQL) queda CERRADO hacia afuera (solo localhost)."

# 5. Proteccion basica contra escaneo de puertos / ping flood
firewall-cmd --zone=$ZONA --add-icmp-block=echo-request --permanent 2>/dev/null || true

# 6. Rate limiting para SSH (mitiga fuerza bruta basica)
#    Limita a 4 intentos de conexion nuevos por minuto por IP
firewall-cmd --zone=$ZONA --remove-service=ssh --permanent
firewall-cmd --permanent --new-service=ssh-limited 2>/dev/null || true
firewall-cmd --permanent --service=ssh-limited --set-description="SSH con rate limit"
firewall-cmd --permanent --service=ssh-limited --add-port=22/tcp
firewall-cmd --zone=$ZONA --add-rich-rule='rule service name="ssh" limit value="4/m" accept' --permanent

# 7. Aplicar todos los cambios
echo "Recargando firewall..."
firewall-cmd --reload

# 8. Mostrar el resultado final
echo ""
echo "=== Configuracion aplicada ==="
firewall-cmd --zone=$ZONA --list-all

echo ""
echo "Listo. Puertos abiertos: 22 (SSH, limitado), 80 (HTTP), 443 (HTTPS)."
echo "Todo lo demas, incluido MySQL (3306), queda bloqueado desde afuera."
