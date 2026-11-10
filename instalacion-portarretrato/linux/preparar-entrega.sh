#!/bin/bash
# Deja el portarretrato "de fábrica" para entregarlo: el dueño nuevo lo enciende, conecta
# su WiFi desde el celular y hace el asistente de primer uso.
#
#   sudo bash /opt/portaretrato/instalacion-portarretrato/linux/preparar-entrega.sh [--borrar-fotos]
#
# Qué hace:
#   - olvida TODAS las redes WiFi guardadas (tu WiFi y su clave no quedan en el equipo)
#   - quita los celulares emparejados por Syncthing (para que el tuyo no siga enviando fotos)
#   - genera una clave inicial nueva (la muestra la pantalla) y reinicia el asistente de primer uso
#   - borra los mensajes y la ciudad/nombre configurados
#   - con --borrar-fotos: también borra todos los álbumes, fotos y videos
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "Correr con sudo"; exit 1; }
APP=/opt/portaretrato
CFG=$APP/backend/config.local.php
BORRAR=0; [ "${1:-}" = "--borrar-fotos" ] && BORRAR=1

echo "Esto deja el equipo listo para entregar y NO se puede deshacer."
[ $BORRAR = 1 ] && echo ">>> También se BORRAN todas las fotos y videos."
read -r -p "Escribí SI para continuar: " ok
[ "$ok" = "SI" ] || { echo "Cancelado."; exit 1; }

# 1) WiFi y clave del WiFi propio (se regenera sola al arrancar el servicio)
/usr/local/bin/pr-admin wifi-forget >/dev/null 2>&1 || true
rm -f /var/lib/portaretrato/ap-clave

# 2) Clave inicial nueva
GPASS=$(head -c 24 /dev/urandom | base64 | tr -dc 'a-z0-9' | head -c 8)
sed -i "s#^define('GESTION_PASSWORD'.*#define('GESTION_PASSWORD', '$GPASS');#" "$CFG"
mysql portaretrato <<SQL
DELETE FROM settings WHERE clave IN ('gestion_pass_hash','familiar_nombre','weather_city','weather_lat','weather_lon','timezone','rotate','visor_off_image');
REPLACE INTO settings (clave, valor) VALUES ('clave_inicial', '$GPASS'), ('setup_done', '0');
DELETE FROM recordatorios;
SQL

# 3) Syncthing: quitar los celulares emparejados
SCFG=/home/frame/.local/state/syncthing/config.xml
if [ -f "$SCFG" ]; then
python3 - "$SCFG" <<'PY'
import json, re, sys, urllib.request
key = re.search(r'<apikey>(.*?)</apikey>', open(sys.argv[1]).read()).group(1)
def api(m, p, b=None):
    r = urllib.request.Request('http://127.0.0.1:8384/rest/' + p, method=m, data=json.dumps(b).encode() if b is not None else None,
                               headers={'X-API-Key': key, 'Content-Type': 'application/json'})
    with urllib.request.urlopen(r, timeout=10) as x:
        t = x.read(); return json.loads(t) if t else None
try:
    me = api('GET', 'system/status')['myID']
    for d in api('GET', 'config/devices'):
        if d['deviceID'] != me: api('DELETE', 'config/devices/' + d['deviceID'])
    print('Syncthing: celulares quitados')
except Exception as e:
    print('Syncthing: no se pudo limpiar (', e, ')')
PY
fi

# 4) Fotos (solo si se pidió)
if [ $BORRAR = 1 ]; then
    mysql portaretrato -e "SET FOREIGN_KEY_CHECKS=0; DELETE FROM media; DELETE FROM quick_show_media; DELETE FROM albums; SET FOREIGN_KEY_CHECKS=1;"
    find "$APP/sync" -mindepth 1 -maxdepth 1 ! -name '.stfolder' -exec rm -rf {} + 2>/dev/null || true
    rm -rf "$APP/sync-papelera"/* "$APP/uploads"/* 2>/dev/null || true
    chown -R frame:frame "$APP/sync" "$APP/uploads" 2>/dev/null || true
fi

echo
echo "Listo. Clave inicial del equipo: $GPASS  (la muestra la pantalla; no hace falta anotarla)"
echo "Ahora apagalo:  sudo poweroff"
echo "En casa del dueño: al encenderlo abre su propio WiFi y la pantalla explica los pasos."
