#!/bin/bash
# Activa HTTPS en el portarretrato con una autoridad propia (CA local) para que el celular
# pueda INSTALAR Gestión como app. Se corre una vez como root en el Debian:
#   bash /opt/portaretrato/instalacion-portarretrato/linux/https.sh [IP]
# Deja el certificado de la CA en /opt/portaretrato/gestion/ca.crt (se instala en el celular).
set -euo pipefail

APP=/opt/portaretrato
DIR=/etc/portaretrato-ssl
HOST=$(hostname)
IP=${1:-$(hostname -I | awk '{print $1}')}

[ "$(id -u)" = 0 ] || { echo "Correr como root"; exit 1; }
apt-get install -y -q openssl >/dev/null
mkdir -p "$DIR"; chmod 700 "$DIR"

# 1. Autoridad certificadora propia (una sola vez)
if [ ! -f "$DIR/ca.key" ]; then
    openssl genrsa -out "$DIR/ca.key" 4096 2>/dev/null
    openssl req -x509 -new -nodes -key "$DIR/ca.key" -sha256 -days 3650 \
        -subj "/CN=Aura Portaretrato CA" -out "$DIR/ca.crt"
fi

# 2. Certificado del servidor (se rehace si cambia la IP)
cat > "$DIR/server.ext" <<EOF
authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
keyUsage=digitalSignature,keyEncipherment
extendedKeyUsage=serverAuth
subjectAltName=DNS:$HOST.local,DNS:$HOST,IP:$IP
EOF
openssl genrsa -out "$DIR/server.key" 2048 2>/dev/null
openssl req -new -key "$DIR/server.key" -subj "/CN=$HOST.local" -out "$DIR/server.csr"
openssl x509 -req -in "$DIR/server.csr" -CA "$DIR/ca.crt" -CAkey "$DIR/ca.key" -CAcreateserial \
    -out "$DIR/server.crt" -days 825 -sha256 -extfile "$DIR/server.ext" 2>/dev/null
chmod 640 "$DIR/server.key"; chgrp frame "$DIR/server.key" 2>/dev/null || true

# 3. El certificado público de la CA, para bajarlo desde el celular
install -o frame -g frame -m 644 "$DIR/ca.crt" "$APP/gestion/ca.crt"

# 4. Apache en el puerto 443
a2enmod ssl >/dev/null
cat > /etc/apache2/sites-available/portaretrato-ssl.conf <<EOF
<VirtualHost *:443>
    ServerName $HOST.local
    DocumentRoot $APP
    SSLEngine on
    SSLCertificateFile $DIR/server.crt
    SSLCertificateKeyFile $DIR/server.key
    <Directory $APP>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/portaretrato-error.log
</VirtualHost>
EOF
a2ensite portaretrato-ssl >/dev/null
systemctl restart apache2
echo "HTTPS listo: https://$HOST.local/gestion/  (o https://$IP/gestion/). CA en $APP/gestion/ca.crt"
