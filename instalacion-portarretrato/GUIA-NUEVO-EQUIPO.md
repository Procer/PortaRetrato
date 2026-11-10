# Armar otro portarretrato (para un amigo)

Arquitectura: **todo corre en el propio Debian del portarretrato** (Apache + PHP + MariaDB + Syncthing + Chromium en kiosk). Cada equipo es independiente: su base, su clave y sus fotos.

Objetivo: vos lo instalás, se lo das, **lo enchufa y anda**. Lo único que hace el dueño es conectar su WiFi desde el celular (guiado por la pantalla) y completar un asistente de 4 datos.

## 1. Instalación (la hacés vos, con cable de red)

Lo normal es usar el **pendrive** (ver `GUIA-PANEL.md`): arrancás la notebook desde él y el resto es automático (Debian + proyecto + WiFi + pantalla + registro en el panel).

A mano, sobre un Debian ya instalado con usuario `frame`:
```
git clone https://github.com/Procer/PortaRetrato.git /opt/portaretrato && chown -R frame:frame /opt/portaretrato
sudo bash /opt/portaretrato/instalacion-portarretrato/linux/finalizar.sh [URL_PANEL CODIGO_ALTA [stable|main]]
```
Instala servidor, red (NetworkManager y WiFi propio), agente del panel, actualización nocturna y la pantalla kiosco. Comprobar con `bash .../linux/diagnostico.sh`.

## 2. Entrega: dejarlo "de fábrica"

Cuando esté probado, en el equipo:

```
sudo bash /opt/portaretrato/instalacion-portarretrato/linux/preparar-entrega.sh            # conserva las fotos que cargaste
sudo bash /opt/portaretrato/instalacion-portarretrato/linux/preparar-entrega.sh --borrar-fotos   # las borra también
sudo poweroff
```

Hace: olvida **todos los WiFi guardados** (el tuyo y su clave no se quedan en el equipo), quita tus celulares de Syncthing (si no, tu teléfono seguiría pudiendo enviarle fotos), genera una clave inicial nueva, borra mensajes/ciudad/nombre y reinicia el asistente. Le entregás el equipo apagado, con su cargador.

## 3. Lo que vive el dueño (probalo vos así antes de entregar)

1. **Enchufa y enciende.** Arranca solo.
2. Como no conoce ningún WiFi, a los ~45 s la pantalla muestra: *"Conectemos tu portarretrato a internet"* con el nombre de un WiFi propio (`Portaretrato-XXXX`), su clave y un **QR**. El dueño escanea el QR con la cámara del celular y se conecta a ese WiFi.
3. El celular abre solo una página (*"Conectar al WiFi"*; si no, `http://10.42.0.1/conectar/`). Elige el WiFi de su casa, escribe la clave y toca **Conectar**.
4. El celular se desconecta del portarretrato (normal). La pantalla dice *Conectando…* y luego *¡Bienvenido! Ya hay internet*, con una dirección, un QR y una **clave inicial**. Si la clave del WiFi estaba mal, la pantalla lo dice y vuelve a mostrar el WiFi propio para reintentar.
5. El dueño vuelve a su WiFi de siempre, abre la dirección (o escanea el QR) e ingresa con la clave inicial. Aparece el **asistente de bienvenida**: clave nueva, ciudad, nombre de quien escribe mensajes, orientación y zona horaria. Termina en la pestaña **Ayuda**.
6. Listo: sube sus fotos desde **Álbumes → +** o enlaza el celular con Syncthing (**Sistema**).

Después, todo se cambia desde **Sistema** (WiFi, clave, orientación, zona horaria, nombre, reiniciar/apagar) y **Pantalla** (reloj, clima, modo noche, horario).

**Si se cae el WiFi de su casa o cambia el router:** a los pocos minutos sin red el equipo vuelve a abrir su WiFi propio y la pantalla explica de nuevo cómo elegir la red. También reintenta solo la red guardada cada 3 minutos (por si el router tardó en arrancar tras un corte de luz).

## 4. Qué NO copiar de tu equipo al de tu amigo

`config.local.php`, la base de datos, `/etc/portaretrato-ssl`, `sync/`, `uploads/`. Las claves de la base y de Gestión se generan solas en cada instalación. Clonar un disco entero es posible, pero **siempre** pasar después por `preparar-entrega.sh` (regenera claves y borra WiFi y celulares).

## 5. Consideraciones

**Hardware**
- La notebook necesita WiFi (o el dueño la conecta por cable; en ese caso no hace falta nada de lo anterior). Verificar con `nmcli dev` que aparezca un dispositivo `wifi` y que soporte modo AP (`iw list | grep -A8 "Supported interface modes"` debe incluir `AP`). Algunas placas viejas o con driver propietario no lo soportan: en ese caso usar un adaptador USB WiFi o cable.
- GPU rara (tirones/tearing): `CHROMIUM_FLAGS="--disable-gpu"` en `/etc/default/portaretrato`.
- Notebook con batería: conviene sacarla si se puede. Para usarla con la tapa cerrada: `HandleLidSwitch=ignore` en `/etc/systemd/logind.conf`.
- Reservar IP fija en el router del dueño es opcional ahora: se usa `portaretrato.local` (algunos Android no lo resuelven; la pantalla y Gestión muestran también la IP).

**Seguridad**
- Todo funciona solo dentro de la red local; no abrir puertos del router.
- El WiFi propio de configuración tiene clave (la muestra la pantalla); solo quien ve la pantalla puede entrar. La página `/conectar/` solo funciona mientras ese WiFi está activo.
- La clave de Gestión se guarda como hash en la base. La clave inicial solo se muestra en pantalla hasta terminar el asistente.
- `/hija/` (mensajes desde el celular de un familiar) no tiene clave: cualquiera conectado a ese WiFi podría escribir. Aceptable en una casa.
- Gestión puede reiniciar/apagar el equipo y cambiar WiFi mediante `/usr/local/bin/pr-admin` (sudo limitado a esa lista de acciones, con validación).

**Respaldos y mantenimiento**
- Fotos en `/opt/portaretrato/sync`, base en MariaDB. Si el disco muere, se pierde todo: que el dueño conserve las originales en su celular (Syncthing en modo *Solo enviar* ya lo garantiza). Opcional: `mysqldump portaretrato > respaldo.sql` + copiar `sync/` a un disco externo.
- Lo borrado desde Gestión va a `sync-papelera/` y se purga a los 30 días.
- `unattended-upgrades` aplica parches de seguridad; una vez al año `apt full-upgrade` y reiniciar.
- Si Chromium se cierra, el kiosk lo reabre; si queda colgado, **Sistema → Reabrir** o Reiniciar.
- Privacidad: el Visor consulta el clima en `api.open-meteo.com` con solo la latitud y longitud de la ciudad; no se sube ninguna foto a servicios externos.

## 6. Pendiente / ideas

1. Probar de punta a punta en una notebook con WiFi real (el WiFi propio, `/conectar/` y el cambio de red están escritos pero **no se pudieron probar sin esa placa**).
2. Backup automático a un disco USB.
3. PIN opcional en `/hija/`.
4. Control físico de 3 botones (ESP32 Bluetooth o teclado inalámbrico hackeado): sin decidir.
