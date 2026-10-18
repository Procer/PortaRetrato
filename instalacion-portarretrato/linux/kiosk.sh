#!/bin/bash
# Sesion grafica del portarretrato (la lanza ~/.xinitrc). Chromium a pantalla completa,
# reabierto automaticamente si se cierra o se cae.
URL="http://127.0.0.1:8080/"
CHROMIUM_FLAGS=""
ROTATE="left"   # normal | left | right | inverted  (pantalla en vertical; se cambia en /etc/default/portaretrato)
[ -f /etc/default/portaretrato ] && . /etc/default/portaretrato

# Sin salvapantallas ni ahorro de energia de pantalla
xset s off
xset -dpms
xset s noblank

# Rotar la pantalla (monitor en vertical)
if [ "$ROTATE" != "normal" ]; then
    OUT="$(xrandr 2>/dev/null | awk '/ connected/{print $1; exit}')"
    [ -n "$OUT" ] && xrandr --output "$OUT" --rotate "$ROTATE"
fi

openbox &
unclutter -idle 0.1 -root &

# Esperar a que el servidor local responda
until curl -sf -o /dev/null "$URL"; do sleep 2; done

while true; do
    # Si Chromium quedo con el perfil bloqueado por un apagado brusco, limpiar
    rm -f "$HOME/chromium-profile/SingletonLock" "$HOME/chromium-profile/SingletonCookie" "$HOME/chromium-profile/SingletonSocket"
    chromium --kiosk --no-first-run --noerrdialogs --disable-infobars \
        --disable-session-crashed-bubble --disable-features=Translate \
        --autoplay-policy=no-user-gesture-required --password-store=basic \
        --user-data-dir="$HOME/chromium-profile" $CHROMIUM_FLAGS "$URL"
    sleep 3
done
