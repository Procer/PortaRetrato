#!/bin/bash
# Diagnostico rapido del portarretrato. Correr como root en el Debian:
#   bash /opt/portaretrato/instalacion-portarretrato/linux/diagnostico.sh
APP=/opt/portaretrato
st() { printf '%-34s %s\n' "$1" "$(systemctl is-active "$1" 2>&1)"; }

echo "=== SERVICIOS ==="
st apache2
st mariadb
st syncthing@frame
st avahi-daemon
st portaretrato-importar.timer

echo "=== RED ==="
hostname -I
printf 'Visor (/)            HTTP %s\n' "$(curl -s -o /dev/null -w '%{http_code}' http://localhost/)"
M="$(curl -s 'http://localhost/backend/api.php?action=get_visor_data')"
printf 'Fotos y videos activos: %s\n' "$(echo "$M" | python3 -c 'import sys,json; print(len(json.load(sys.stdin)["media"]))' 2>/dev/null || echo '?')"

echo "=== DISCO ==="
df -h / | tail -1
du -sh $APP/sync $APP/uploads 2>/dev/null

echo "=== ULTIMO IMPORT (agente/importar.log) ==="
tail -n 6 $APP/agente/importar.log 2>/dev/null | cut -c1-150 || echo "(sin log)"

echo "=== GRAFICA / FUENTES ==="
lspci 2>/dev/null | grep -iE 'vga|3d|display' | cut -c1-120
printf 'Fuente Inter:        %s\n' "$(fc-list 2>/dev/null | grep -qi 'inter' && echo OK || echo 'FALTA (apt install fonts-inter)')"
printf 'Fuente de emojis:    %s\n' "$(fc-list 2>/dev/null | grep -qi 'emoji' && echo OK || echo 'FALTA (apt install fonts-noto-color-emoji)')"
if [ -f /var/log/Xorg.0.log ] || [ -f "/home/frame/.local/share/xorg/Xorg.0.log" ]; then
    grep -hE '\(EE\)|\(WW\).*(driver|modeset|GLX)' /var/log/Xorg.0.log /home/frame/.local/share/xorg/Xorg.0.log 2>/dev/null | grep -v 'Failed to load module.*(not exist' | tail -n 6 | cut -c1-160
fi

echo "=== ERRORES DE APACHE ==="
tail -n 5 /var/log/apache2/portaretrato-error.log 2>/dev/null | cut -c1-200
