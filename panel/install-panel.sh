#!/bin/bash
# Instala el panel de administración en un VPS Debian/Ubuntu (DonWeb u otro).
#   sudo bash install-panel.sh panel.tudominio.com  'ClaveDeAdministrador'
#
# Antes: en el DNS de tu dominio crear un registro A  panel.tudominio.com -> IP del VPS,
# y en el firewall del VPS dejar abiertos los puertos 80 y 443.
# Es repetible: no pisa la base ni el código de alta.
set -euo pipefail
DOM="${1:-}"; PASS="${2:-}"
[ "$(id -u)" = 0 ] || { echo "Correr con sudo"; exit 1; }
[ -n "$DOM" ] || { echo "Uso: install-panel.sh panel.tudominio.com 'clave'"; exit 1; }
DIR=/opt/portaretrato-panel
DATA=/var/lib/portaretrato-panel

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -q git curl caddy php-fpm php-sqlite3 php-curl php-mbstring >/dev/null

[ -d "$DIR/.git" ] || git clone --depth 1 https://github.com/Procer/PortaRetrato.git "$DIR"
git -C "$DIR" pull --quiet || true
mkdir -p "$DATA"; chown -R www-data:www-data "$DATA"; chmod 750 "$DATA"

# Base, código de alta y clave de administrador (como www-data para que la base le pertenezca)
if [ -n "$PASS" ]; then sudo -u www-data php "$DIR/panel/setup.php" "$PASS"; else sudo -u www-data php "$DIR/panel/setup.php"; fi

SOCK="$(ls /run/php/php*-fpm.sock | head -n1)"
cat > /etc/caddy/Caddyfile <<EOF
$DOM {
    root * $DIR/panel/public
    encode gzip
    php_fastcgi unix/$SOCK
    file_server
    header {
        X-Content-Type-Options nosniff
        X-Frame-Options DENY
        Referrer-Policy no-referrer
        Strict-Transport-Security "max-age=31536000"
    }
}
EOF
systemctl enable --now php*-fpm >/dev/null 2>&1 || true
systemctl restart caddy

# El panel se actualiza solo cada noche desde GitHub (rama main)
cat > /etc/cron.d/portaretrato-panel <<EOF
17 4 * * * root git -C $DIR pull --quiet
EOF

echo
echo "Panel listo:  https://$DOM/"
echo "Código de alta para el pendrive (PANEL_ENROLL):  $(sudo -u www-data php -r 'require "'$DIR'/panel/lib.php"; echo cfg("enroll_code");')"
echo "(también está en el panel → Ajustes)"
