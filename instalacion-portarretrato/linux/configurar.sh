#!/bin/bash
# Pantalla del portarretrato: Xorg + Openbox + Chromium a pantalla completa, con inicio automático.
# (El servidor —Apache, PHP, base, Syncthing, red/WiFi, panel— lo instala instalar-servidor.sh.)
# Se corre dentro de instalar-todo.sh; también se puede repetir a mano:
#   sudo bash /opt/portaretrato/instalacion-portarretrato/linux/configurar.sh
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "Correr como root"; exit 1; }
APP=/opt/portaretrato
USER_NAME=frame

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y --no-install-recommends \
    xserver-xorg-core xserver-xorg-input-libinput xserver-xorg-video-all xinit x11-xserver-utils \
    openbox unclutter chromium pipewire-audio unattended-upgrades \
    fonts-dejavu-core fonts-inter fonts-liberation2 fonts-noto-color-emoji mesa-utils

id "$USER_NAME" >/dev/null 2>&1 || useradd -m -s /bin/bash "$USER_NAME"
usermod -aG video,audio,render,input "$USER_NAME" 2>/dev/null || usermod -aG video,audio "$USER_NAME"

# Opciones del Visor (las cambia Gestión → Sistema; también se pueden editar a mano)
[ -f /etc/default/portaretrato ] || cat > /etc/default/portaretrato <<'EOF'
# Opciones extra para Chromium, ej: --disable-gpu (si la placa de video da problemas)
CHROMIUM_FLAGS=""
# Orientacion de la pantalla: normal | left | right | inverted
ROTATE="normal"
EOF

# Chromium sin avisos de traducción, contraseñas ni telemetría
mkdir -p /etc/chromium/policies/managed
cp "$APP/instalacion-portarretrato/linux/chromium-policy.json" /etc/chromium/policies/managed/portaretrato.json

# ─── Inicio de sesión automático en tty1 → X → Chromium kiosco ───────────────
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

# La consola nunca se apaga sola; la tapa cerrada no suspende el equipo
if [ -f /etc/default/grub ] && ! grep -q consoleblank /etc/default/grub; then
    sed -i 's/^GRUB_CMDLINE_LINUX_DEFAULT="\(.*\)"/GRUB_CMDLINE_LINUX_DEFAULT="\1 consoleblank=0"/' /etc/default/grub
    update-grub 2>/dev/null || true
fi
mkdir -p /etc/systemd/logind.conf.d
printf '[Login]\nHandleLidSwitch=ignore\nHandleLidSwitchExternalPower=ignore\nHandleLidSwitchDocked=ignore\nIdleAction=ignore\n' > /etc/systemd/logind.conf.d/portaretrato.conf

systemctl enable ssh >/dev/null 2>&1 || true
echo "Pantalla configurada."
