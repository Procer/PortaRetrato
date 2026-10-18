#!/bin/bash
# Paso final tras una instalacion MANUAL de Debian: lee los datos del hosting desde el pendrive
# de Ventoy (ventoy/script/preseed.cfg) y deja el portarretrato configurado.
# Uso (con el pendrive conectado):  sudo bash /opt/portaretrato/instalacion-portarretrato/linux/finalizar.sh
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "Correr con sudo"; exit 1; }
HERE="$(cd "$(dirname "$0")" && pwd)"

DEV="$(blkid -L Ventoy 2>/dev/null || true)"
[ -n "$DEV" ] || { echo "No encuentro el pendrive de Ventoy. Conectalo y reintenta."; exit 1; }
MNT=/mnt/ventoy
mkdir -p "$MNT"
mountpoint -q "$MNT" || mount -o ro "$DEV" "$MNT"

PRESEED="$MNT/ventoy/script/preseed.cfg"
[ -f "$PRESEED" ] || { echo "No esta $PRESEED en el pendrive"; exit 1; }
B64="$(sed -n 's/.*echo \([A-Za-z0-9+\/=]*\) | base64 -d.*/\1/p' "$PRESEED" | head -n1)"
[ -n "$B64" ] || { echo "No pude leer los datos del hosting del preseed.cfg"; exit 1; }
echo "$B64" | base64 -d > /root/pr.env
chmod 600 /root/pr.env
grep -q '^PR_TOKEN=' /root/pr.env && grep -q '^PR_REMOTE=' /root/pr.env || { echo "Datos del hosting incompletos"; exit 1; }
umount "$MNT"
echo "Datos del hosting leidos del pendrive (ya podes sacarlo despues de reiniciar)."

bash "$HERE/configurar.sh"
echo
echo "LISTO. Ahora reinicia con:  sudo reboot   (y saca el pendrive)"
