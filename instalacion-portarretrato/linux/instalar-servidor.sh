#!/bin/bash
# Deja el Debian del portarretrato como servidor completo: Apache + PHP + MariaDB + Syncthing
# + importador. Se corre una vez como root, con el proyecto ya copiado en /opt/portaretrato:
#   bash /opt/portaretrato/instalacion-portarretrato/linux/instalar-servidor.sh
# Es repetible: no pisa la clave de Gestión ni la base si ya existen.
set -euo pipefail

APP=/opt/portaretrato
USR=frame
SYNC=$APP/sync
HOSTNAME_NEW=portaretrato
TZ_NEW=America/Argentina/Buenos_Aires

[ "$(id -u)" = 0 ] || { echo "Correr como root"; exit 1; }
[ -f "$APP/schema.sql" ] || { echo "Falta $APP/schema.sql"; exit 1; }

export DEBIAN_FRONTEND=noninteractive
apt-get install -y -q apache2 libapache2-mod-php mariadb-server php-mysql php-gd php-mbstring \
    php-xml php-curl php-zip syncthing avahi-daemon ffmpeg python3 >/dev/null

# ── Nombre en la red (portaretrato.local) y zona horaria ─────────────────────
hostnamectl set-hostname "$HOSTNAME_NEW"
sed -i "s/^127\.0\.1\.1.*/127.0.1.1\t$HOSTNAME_NEW/" /etc/hosts
grep -q "127.0.1.1" /etc/hosts || echo -e "127.0.1.1\t$HOSTNAME_NEW" >> /etc/hosts
timedatectl set-timezone "$TZ_NEW"
systemctl enable --now avahi-daemon >/dev/null 2>&1

# ── Carpetas ─────────────────────────────────────────────────────────────────
mkdir -p "$SYNC/.stfolder" "$APP/uploads"
chown -R $USR:$USR "$APP"

# ── PHP ──────────────────────────────────────────────────────────────────────
cat > /etc/php/8.4/apache2/conf.d/99-portaretrato.ini <<EOF
upload_max_filesize = 4G
post_max_size = 4G
max_file_uploads = 200
max_execution_time = 0
max_input_time = -1
memory_limit = 512M
date.timezone = $TZ_NEW
EOF

# ── Apache (corre como $USR para compartir permisos con el importador) ──────
a2enmod rewrite headers expires deflate >/dev/null
a2dissite 000-default >/dev/null 2>&1 || true
sed -i "s/^export APACHE_RUN_USER=.*/export APACHE_RUN_USER=$USR/; s/^export APACHE_RUN_GROUP=.*/export APACHE_RUN_GROUP=$USR/" /etc/apache2/envvars
cat > /etc/apache2/sites-available/portaretrato.conf <<EOF
<VirtualHost *:80>
    ServerName $HOSTNAME_NEW.local
    DocumentRoot $APP
    <Directory $APP>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/portaretrato-error.log
</VirtualHost>
EOF
a2ensite portaretrato >/dev/null
systemctl enable apache2 >/dev/null 2>&1
systemctl restart apache2

# ── Base de datos y config.local.php ────────────────────────────────────────
systemctl enable --now mariadb >/dev/null 2>&1
CFG=$APP/backend/config.local.php
if [ ! -f "$CFG" ]; then
    DBPASS=$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 24)
    GPASS=${GESTION_PASSWORD:-$(head -c 12 /dev/urandom | base64 | tr -dc 'a-z0-9' | head -c 8)}
    mysql -e "CREATE DATABASE IF NOT EXISTS portaretrato CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
              CREATE USER IF NOT EXISTS 'portaretrato'@'localhost' IDENTIFIED BY '$DBPASS';
              ALTER USER 'portaretrato'@'localhost' IDENTIFIED BY '$DBPASS';
              GRANT ALL ON portaretrato.* TO 'portaretrato'@'localhost'; FLUSH PRIVILEGES;"
    cat > "$CFG" <<EOF
<?php
define('GESTION_PASSWORD', '$GPASS');
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'portaretrato');
define('DB_USER', 'portaretrato');
define('DB_PASS', '$DBPASS');
define('SYNC_DIR', '$SYNC');
EOF
    chown $USR:$USR "$CFG"; chmod 640 "$CFG"
    echo ">>> Clave de Gestión: $GPASS"
fi
mysql portaretrato < "$APP/schema.sql"

# ── Importador: cada minuto ─────────────────────────────────────────────────
cat > /etc/systemd/system/portaretrato-importar.service <<EOF
[Unit]
Description=Portaretrato - importar la carpeta sincronizada
After=mariadb.service

[Service]
Type=oneshot
User=$USR
ExecStart=/usr/bin/php $APP/agente/importar.php
EOF
cat > /etc/systemd/system/portaretrato-importar.timer <<EOF
[Unit]
Description=Portaretrato - importar cada minuto

[Timer]
OnBootSec=30s
OnUnitActiveSec=60s

[Install]
WantedBy=timers.target
EOF
systemctl daemon-reload
systemctl enable --now portaretrato-importar.timer >/dev/null 2>&1

# ── Syncthing ───────────────────────────────────────────────────────────────
loginctl enable-linger $USR >/dev/null 2>&1 || true
systemctl enable --now syncthing@$USR >/dev/null 2>&1
for i in $(seq 1 30); do
    SCFG=/home/$USR/.local/state/syncthing/config.xml
    [ -f "$SCFG" ] && curl -sf -o /dev/null -H "X-API-Key: $(sed -n 's#.*<apikey>\(.*\)</apikey>.*#\1#p' "$SCFG")" http://127.0.0.1:8384/rest/system/ping && break
    sleep 2
done
python3 - "$SCFG" "$SYNC" <<'PY'
import json, re, sys, urllib.request
cfg, sync = sys.argv[1], sys.argv[2]
key = re.search(r'<apikey>(.*?)</apikey>', open(cfg).read()).group(1)
def api(method, path, body=None):
    req = urllib.request.Request('http://127.0.0.1:8384/rest/' + path, method=method,
        data=json.dumps(body).encode() if body is not None else None,
        headers={'X-API-Key': key, 'Content-Type': 'application/json'})
    with urllib.request.urlopen(req) as r:
        t = r.read()
        return json.loads(t) if t else None
me = api('GET', 'system/status')['myID']
api('PATCH', 'config/options', {'urAccepted': -1, 'startBrowser': False, 'natEnabled': True,
                                 'globalAnnounceEnabled': True, 'relaysEnabled': True})
folders = {f['id'] for f in api('GET', 'config/folders')}
if 'default' in folders:
    api('DELETE', 'config/folders/default')
api('PUT', 'config/folders/fotos', {
    'id': 'fotos', 'label': 'Fotos', 'path': sync, 'type': 'sendreceive',
    'fsWatcherEnabled': True, 'rescanIntervalS': 60, 'ignorePerms': True,
    'ignoreDelete': True,   # borrar fotos del celular no las borra del portarretrato
    'versioning': {'type': 'trashcan', 'params': {'cleanoutDays': '30'}},
    'devices': [{'deviceID': me}],
})
print('Syncthing listo. ID de este equipo:', me)
PY

echo "Servidor listo: http://$HOSTNAME_NEW.local/  (Gestión: /gestion/)"
