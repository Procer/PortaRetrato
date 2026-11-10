#!/bin/bash
# Instalación completa del portarretrato sobre un Debian recién instalado:
#   servidor + red/WiFi + panel/actualización nocturna  (instalar-servidor.sh)
#   pantalla en modo kiosco                             (configurar.sh)
# Lo corre solo el primer arranque tras instalar desde el pendrive (firstboot.sh);
# también se puede correr a mano:  sudo bash /opt/portaretrato/instalacion-portarretrato/linux/instalar-todo.sh
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "Correr como root"; exit 1; }
HERE="$(cd "$(dirname "$0")" && pwd)"
bash "$HERE/instalar-servidor.sh"
bash "$HERE/configurar.sh"
echo
echo "=== Instalación completa ==="
