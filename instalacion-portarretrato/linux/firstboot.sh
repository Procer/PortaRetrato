#!/bin/bash
# Primer arranque tras instalar Debian desde el pendrive. Lo lanza portaretrato-firstboot.service.
# Descarga el proyecto, instala todo y reinicia. Si algo falla (por ejemplo, sin internet) se
# reintenta solo en el próximo arranque, hasta que termine bien.
LOG=/var/log/portaretrato-firstboot.log
exec > >(tee -a "$LOG" /dev/tty1 2>/dev/null) 2>&1
APP=/opt/portaretrato
set -u
[ -f /root/pr.env ] && . /root/pr.env

echo "=== Portaretrato: instalando (no apagues el equipo) ==="
for i in $(seq 1 90); do curl -sf -o /dev/null https://github.com && break; echo "Esperando internet... ($i)"; sleep 5; done
curl -sf -o /dev/null https://github.com || { echo "Sin internet: conectá el cable de red. Se reintenta al reiniciar."; exit 1; }

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq && apt-get install -y -q git ca-certificates >/dev/null
if [ ! -d "$APP/.git" ]; then
    rm -rf "$APP"
    git clone https://github.com/Procer/PortaRetrato.git "$APP" || { echo "No pude bajar el proyecto"; exit 1; }
fi
chown -R frame:frame "$APP"
# Canal de actualización: los clientes usan la rama stable (si ya existe)
if [ "${PANEL_CHANNEL:-stable}" = stable ] && runuser -u frame -- git -C "$APP" fetch -q origin stable 2>/dev/null; then
    runuser -u frame -- git -C "$APP" checkout -q -B stable FETCH_HEAD
fi

if bash "$APP/instalacion-portarretrato/linux/instalar-todo.sh"; then
    systemctl disable portaretrato-firstboot.service >/dev/null 2>&1
    rm -f /etc/systemd/system/portaretrato-firstboot.service
    echo "Listo. Reiniciando en 10 segundos..."
    sleep 10
    systemctl reboot
else
    echo "La instalación falló. Se reintenta al reiniciar. Detalle en $LOG"
    exit 1
fi
