#!/bin/bash
# Instalacion MANUAL (Debian ya instalado a mano, sin el pendrive automatico):
#   sudo git clone https://github.com/Procer/PortaRetrato.git /opt/portaretrato
#   sudo chown -R frame:frame /opt/portaretrato
#   sudo bash /opt/portaretrato/instalacion-portarretrato/linux/finalizar.sh [URL_PANEL CODIGO_ALTA [stable|main]]
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "Correr con sudo"; exit 1; }
HERE="$(cd "$(dirname "$0")" && pwd)"
if [ -n "${1:-}" ]; then
    printf "PANEL_URL='%s'\nPANEL_ENROLL='%s'\nPANEL_CHANNEL='%s'\n" "$1" "${2:-}" "${3:-stable}" > /root/pr.env
    chmod 600 /root/pr.env
fi
bash "$HERE/instalar-todo.sh"
echo
echo "LISTO. Reinicia con:  sudo reboot"
