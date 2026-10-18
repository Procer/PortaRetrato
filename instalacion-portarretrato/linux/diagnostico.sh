#!/bin/bash
# Resumen del estado del portarretrato en una pantalla (sacarle foto y mandarla).
APP=/opt/portaretrato
st() { printf '%-34s %s\n' "$1" "$(systemctl is-active "$1" 2>/dev/null)"; }
echo "=== SERVICIOS ==="
st portaretrato-server.service
st portaretrato-sync.timer
printf '%-34s %s\n' "chromium corriendo" "$(pgrep -c chromium || echo 0) procesos"
echo "=== SERVIDOR LOCAL ==="
printf 'Visor (/)            HTTP %s\n' "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8080/)"
M="$(curl -s 'http://127.0.0.1:8080/backend/api.php?action=get_sync_manifest')"
printf 'Fotos en el manifest: %s\n' "$(echo "$M" | grep -o '"ruta"' | wc -l)"
echo "Manifest (inicio): $(echo "$M" | cut -c1-110)"
echo "=== ARCHIVOS ==="
printf 'Archivos en uploads/: %s\n' "$(find $APP/uploads -type f 2>/dev/null | wc -l)"
ls -la $APP/backend/mirror.config.php 2>&1 | cut -c1-70
echo "=== ULTIMO SYNC (mirror/sync.log) ==="
tail -n 6 $APP/mirror/sync.log 2>/dev/null | cut -c1-150 || echo "(sin log)"
echo "=== PROBAR SYNC AHORA ==="
timeout 120 php $APP/agente/sync.php; echo "codigo de salida: $?"
echo "=== SISTEMA ==="
free -m | awk 'NR==2{print "RAM usada/total MB: "$3"/"$2}'
df -h / | awk 'NR==2{print "Disco: "$3" usados de "$2}'
