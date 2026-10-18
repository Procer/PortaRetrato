#!/bin/bash
# Deja un Debian recien instalado funcionando como portarretrato:
#   servidor local (PHP) + sincronizacion cada 60 s + Chromium a pantalla completa al encender.
# Lo corre el instalador automatico (preseed) dentro del sistema nuevo, pero se puede volver
# a correr a mano:  sudo bash /opt/portaretrato/instalacion-portarretrato/linux/configurar.sh
# Lee PR_REMOTE y PR_TOKEN de /root/pr.env
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "Correr como root"; exit 1; }

APP=/opt/portaretrato
USER_NAME=frame
[ -f /root/pr.env ] && . /root/pr.env
: "${PR_REMOTE:?Falta PR_REMOTE en /root/pr.env}"
: "${PR_TOKEN:?Falta PR_TOKEN en /root/pr.env}"

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y --no-install-recommends \
    php-cli php-curl php-mbstring git curl ca-certificates \
    xserver-xorg-core xserver-xorg-input-libinput xserver-xorg-video-all xinit x11-xserver-utils \
    openbox unclutter chromium pipewire-audio fonts-dejavu-core unattended-upgrades

id "$USER_NAME" >/dev/null 2>&1 || useradd -m -s /bin/bash "$USER_NAME"
usermod -aG video,audio,render,input "$USER_NAME" 2>/dev/null || usermod -aG video,audio "$USER_NAME"

# ─── Configuracion del espejo ────────────────────────────────────────────────
mkdir -p "$APP/uploads" "$APP/mirror"
# var_export escapa bien cualquier caracter raro del token
PR_REMOTE="$PR_REMOTE" PR_TOKEN="$PR_TOKEN" CFG="$APP/backend/mirror.config.php" php -r     'file_put_contents(getenv("CFG"), "<?php
return " . var_export(["remote" => getenv("PR_REMOTE"), "token" => getenv("PR_TOKEN")], true) . ";
");'
chmod 640 "$APP/backend/mirror.config.php"
chown -R "$USER_NAME:$USER_NAME" "$APP"
[ -f /etc/default/portaretrato ] || echo '# Opciones extra para Chromium, ej: --disable-gpu (si la placa de video da problemas)
CHROMIUM_FLAGS=""
# Orientacion de la pantalla: normal | left | right | inverted
ROTATE="left"' > /etc/default/portaretrato

# ─── Servicios ───────────────────────────────────────────────────────────────
cat > /etc/systemd/system/portaretrato-server.service <<EOF
[Unit]
Description=Portaretrato - servidor local
After=network.target

[Service]
User=$USER_NAME
WorkingDirectory=$APP
ExecStart=/usr/bin/php -S 127.0.0.1:8080 -t $APP
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/systemd/system/portaretrato-sync.service <<EOF
[Unit]
Description=Portaretrato - sincronizacion con el hosting
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
User=$USER_NAME
WorkingDirectory=$APP
ExecStart=/usr/bin/php $APP/agente/sync.php
SuccessExitStatus=1 2
TimeoutStartSec=3h
EOF

cat > /etc/systemd/system/portaretrato-sync.timer <<EOF
[Unit]
Description=Portaretrato - sincronizar cada minuto

[Timer]
OnBootSec=15s
OnUnitActiveSec=60s
AccuracySec=1s

[Install]
WantedBy=timers.target
EOF

# Actualiza el codigo desde GitHub una vez por dia (no toca fotos ni la config local)
cat > /etc/systemd/system/portaretrato-update.service <<EOF
[Unit]
Description=Portaretrato - actualizar codigo
After=network-online.target

[Service]
Type=oneshot
User=$USER_NAME
ExecStart=/usr/bin/git -C $APP pull --ff-only
SuccessExitStatus=1 128
EOF
cat > /etc/systemd/system/portaretrato-update.timer <<EOF
[Unit]
Description=Portaretrato - actualizar codigo a diario

[Timer]
OnCalendar=*-*-* 04:00
RandomizedDelaySec=15m
Persistent=true

[Install]
WantedBy=timers.target
EOF

# ─── Inicio de sesion automatico en tty1 → X → Chromium kiosco ───────────────
mkdir -p /etc/systemd/system/getty@tty1.service.d
cat > /etc/systemd/system/getty@tty1.service.d/autologin.conf <<EOF
[Service]
ExecStart=
ExecStart=-/sbin/agetty --autologin $USER_NAME --noclear %I \$TERM
EOF

cat > "/home/$USER_NAME/.bash_profile" <<'EOF'
if [ -z "$DISPLAY" ] && [ "$(tty)" = /dev/tty1 ]; then
    exec startx -- -nocursor
fi
EOF
echo "exec /bin/bash $APP/instalacion-portarretrato/linux/kiosk.sh" > "/home/$USER_NAME/.xinitrc"
chown "$USER_NAME:$USER_NAME" "/home/$USER_NAME/.bash_profile" "/home/$USER_NAME/.xinitrc"

# La consola nunca se apaga sola
if [ -f /etc/default/grub ] && ! grep -q consoleblank /etc/default/grub; then
    sed -i 's/^GRUB_CMDLINE_LINUX_DEFAULT="\(.*\)"/GRUB_CMDLINE_LINUX_DEFAULT="\1 consoleblank=0"/' /etc/default/grub
    update-grub 2>/dev/null || true
fi

systemctl enable portaretrato-server.service portaretrato-sync.timer portaretrato-update.timer ssh >/dev/null 2>&1 || true
if [ -d /run/systemd/system ]; then
    systemctl daemon-reload
    systemctl restart portaretrato-server.service portaretrato-sync.timer portaretrato-update.timer
fi

# Primera sincronizacion ahora, para que al primer arranque ya haya fotos (no es fatal si falla)
runuser -u "$USER_NAME" -- /usr/bin/php "$APP/agente/sync.php" || echo "Primera sincronizacion pendiente (se reintenta cada minuto)"
echo "Portaretrato configurado."
