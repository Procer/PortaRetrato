#!/bin/bash
# Servicio portaretrato-red: mantiene el equipo conectado y, si no hay red, abre su propio
# WiFi ("Portaretrato-XXXX") para que el dueño lo configure desde el celular.
#
# Estado para el resto del sistema, en /run/portaretrato:
#   modo        online | ap | conectando | sin-red
#   setup.json  datos del WiFi propio (solo mientras modo=ap): ssid, clave, url
#   redes.json  redes WiFi vistas antes de abrir el WiFi propio
#   pendiente   pedido de conexion (SSID y clave) que deja `pr-admin wifi-connect`
#   error       ultimo error de conexion (lo muestra la pantalla)
set -u
RUN=/run/portaretrato
STATE=/var/lib/portaretrato
AP_CON="Portaretrato-AP"
AP_IP="10.42.0.1"
mkdir -p "$RUN" "$STATE"
chmod 755 "$RUN"

# Nombre y clave del WiFi propio: fijos para cada equipo (no cambian entre arranques)
ID="$(cut -c1-4 /etc/machine-id | tr a-f A-F)"
AP_SSID="Portaretrato-$ID"
if [ ! -s "$STATE/ap-clave" ]; then
    head -c 64 /dev/urandom | tr -dc '0-9' | head -c 8 > "$STATE/ap-clave"
fi
AP_PASS="$(cat "$STATE/ap-clave")"

set_modo() { echo "$1" > "$RUN/modo"; chmod 644 "$RUN/modo"; }
wifi_dev() { nmcli -t -f DEVICE,TYPE dev 2>/dev/null | awk -F: '$2=="wifi"{print $1; exit}'; }
ap_activo() { nmcli -t -f NAME con show --active 2>/dev/null | grep -qx "$AP_CON"; }
tiene_red() { ip route show default 2>/dev/null | grep -q . && ! ap_activo; }

escanear() {
    nmcli dev wifi rescan >/dev/null 2>&1; sleep 4
    nmcli -t -f SSID,SIGNAL,SECURITY dev wifi list 2>/dev/null | python3 -c '
import sys, json
seen = {}
for l in sys.stdin:
    p = l.rstrip("\n").replace("\\:", "\x00").split(":")
    if len(p) < 3 or not p[0].strip(): continue
    n = p[0].replace("\x00", ":")
    s = int(p[1] or 0)
    if n not in seen or s > seen[n][0]: seen[n] = (s, p[2] not in ("", "--"))
print(json.dumps([{"ssid": k, "senal": v[0], "clave": v[1]} for k, v in sorted(seen.items(), key=lambda x: -x[1][0])]))' > "$RUN/redes.json"
    chmod 644 "$RUN/redes.json"
}

abrir_ap() {
    local dev; dev="$(wifi_dev)"
    [ -n "$dev" ] || return 1
    escanear
    nmcli con delete "$AP_CON" >/dev/null 2>&1
    nmcli con add type wifi ifname "$dev" con-name "$AP_CON" autoconnect no ssid "$AP_SSID" \
        802-11-wireless.mode ap 802-11-wireless.band bg \
        ipv4.method shared ipv4.addresses "$AP_IP/24" \
        wifi-sec.key-mgmt wpa-psk wifi-sec.psk "$AP_PASS" >/dev/null 2>&1
    nmcli con up "$AP_CON" >/dev/null 2>&1 || return 1
    python3 - "$AP_SSID" "$AP_PASS" "$AP_IP" > "$RUN/setup.json" <<'PY'
import json, sys
print(json.dumps({"ssid": sys.argv[1], "clave": sys.argv[2], "url": "http://%s/conectar/" % sys.argv[3]}))
PY
    chmod 644 "$RUN/setup.json"
    set_modo ap
}

cerrar_ap() {
    nmcli con down "$AP_CON" >/dev/null 2>&1
    rm -f "$RUN/setup.json"
}

# Aplica el pedido que dejo la pagina /conectar/ o Gestion
procesar_pendiente() {
    [ -f "$RUN/pendiente" ] || return 1
    local ssid pass
    { IFS= read -r ssid; IFS= read -r pass; } < "$RUN/pendiente"
    rm -f "$RUN/pendiente"
    set_modo conectando
    sleep 3                      # deja que el celular reciba la respuesta antes de cortar su WiFi
    cerrar_ap
    nmcli con delete id "$ssid" >/dev/null 2>&1
    local out
    if [ -n "$pass" ]; then out="$(nmcli --wait 40 dev wifi connect "$ssid" password "$pass" 2>&1)"
    else out="$(nmcli --wait 40 dev wifi connect "$ssid" 2>&1)"; fi
    if [ $? -eq 0 ]; then
        rm -f "$RUN/error"; set_modo online
    else
        nmcli con delete id "$ssid" >/dev/null 2>&1
        case "$out" in
            *ecret*|*assword*|*auth*) echo "La clave del WiFi \"$ssid\" no es correcta." > "$RUN/error" ;;
            *) echo "No se pudo conectar a \"$ssid\". Revisá el nombre y la clave." > "$RUN/error" ;;
        esac
        chmod 644 "$RUN/error"
        abrir_ap
    fi
    return 0
}

hay_perfiles_wifi() { nmcli -t -f TYPE,NAME con show 2>/dev/null | grep '^802-11-wireless' | grep -qv ":$AP_CON\$"; }

set_modo desconocido
sin_red=0
ap_desde=0
while true; do
    procesar_pendiente && { sin_red=0; ap_desde=$(date +%s); continue; }

    if tiene_red; then
        sin_red=0
        [ "$(cat $RUN/modo 2>/dev/null)" = online ] || { set_modo online; rm -f "$RUN/setup.json"; }
    elif ap_activo; then
        # En modo WiFi propio: cada 3 minutos, si nadie lo esta usando y hay una red guardada
        # (por ejemplo el router tardo en arrancar), probar volver a conectarse a ella.
        if [ $(( $(date +%s) - ap_desde )) -ge 180 ] && hay_perfiles_wifi; then
            dev="$(wifi_dev)"
            if [ "$(iw dev "$dev" station dump 2>/dev/null | grep -c '^Station')" = 0 ]; then
                cerrar_ap; sleep 20
                tiene_red && { set_modo online; sin_red=0; continue; }
                abrir_ap
            fi
            ap_desde=$(date +%s)
        fi
    else
        sin_red=$((sin_red + 1))
        # ~45 s sin red (el arranque normal tarda menos) => abrir el WiFi propio
        if [ $sin_red -ge 9 ]; then
            if [ -n "$(wifi_dev)" ]; then
                abrir_ap && ap_desde=$(date +%s) || set_modo sin-red
            else
                set_modo sin-red
            fi
            sin_red=0
        fi
    fi
    sleep 5
done
