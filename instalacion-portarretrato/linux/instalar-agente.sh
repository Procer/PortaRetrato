#!/bin/bash
# Instala el agente del panel de administración y la actualización nocturna.
#   sudo bash instalar-agente.sh [URL_DEL_PANEL CODIGO_DE_ALTA [stable|main]]
# Sin argumentos usa PANEL_URL / PANEL_ENROLL / PANEL_CHANNEL de /root/pr.env (lo deja el pendrive).
# Sirve también para sumar al panel un equipo que ya estaba instalado.
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "Correr con sudo"; exit 1; }
APP=/opt/portaretrato
[ -f /root/pr.env ] && . /root/pr.env
if [ -n "${1:-}" ]; then PANEL_URL="$1"; PANEL_ENROLL="${2:-}"; PANEL_CHANNEL="${3:-stable}"; rm -f /var/lib/portaretrato/panel.json; fi

install -m 755 "$APP/instalacion-portarretrato/linux/pr-actualizar" /usr/local/bin/pr-actualizar
mkdir -p /var/lib/portaretrato
if [ -n "${PANEL_URL:-}" ] && [ ! -f /var/lib/portaretrato/panel.json ]; then
    PANEL_URL="$PANEL_URL" PANEL_ENROLL="${PANEL_ENROLL:-}" PANEL_CHANNEL="${PANEL_CHANNEL:-stable}" php -r '
        file_put_contents("/var/lib/portaretrato/panel.json", json_encode(["url" => getenv("PANEL_URL"), "enroll" => getenv("PANEL_ENROLL"), "channel" => getenv("PANEL_CHANNEL")], JSON_PRETTY_PRINT));'
    chmod 600 /var/lib/portaretrato/panel.json
    echo "${PANEL_CHANNEL:-stable}" > /var/lib/portaretrato/channel
fi
cat > /etc/systemd/system/portaretrato-agente.service <<EOF2
[Unit]
Description=Portaretrato - aviso al panel de administracion
After=network-online.target mariadb.service

[Service]
Type=oneshot
ExecStart=/usr/bin/php $APP/agente/panel_agent.php
EOF2
cat > /etc/systemd/system/portaretrato-agente.timer <<EOF2
[Unit]
Description=Portaretrato - avisar al panel cada 10 minutos

[Timer]
OnBootSec=2min
OnUnitActiveSec=10min
RandomizedDelaySec=60

[Install]
WantedBy=timers.target
EOF2
cat > /etc/systemd/system/portaretrato-actualizar.service <<EOF2
[Unit]
Description=Portaretrato - actualizacion nocturna
After=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/local/bin/pr-actualizar
EOF2
cat > /etc/systemd/system/portaretrato-actualizar.timer <<EOF2
[Unit]
Description=Portaretrato - actualizar cada noche

[Timer]
OnCalendar=*-*-* 03:30:00
RandomizedDelaySec=45min
Persistent=true

[Install]
WantedBy=timers.target
EOF2
systemctl daemon-reload
systemctl enable --now portaretrato-agente.timer portaretrato-actualizar.timer >/dev/null 2>&1
echo "Agente instalado."
