# Aura — Porta Retrato

Un portarretrato digital que corre **todo en un solo equipo** (una PC con Debian): muestra el Visor a pantalla completa, guarda las fotos y se administra desde el celular dentro de la red local. No depende de ningún sitio web ni servicio en la nube.

## Cómo funciona

| Pieza | Qué hace |
|---|---|
| **Visor** (`/`) | Pantalla completa: fotos, videos, reloj, clima y mensajes. Chromium en modo kiosk apunta a `http://localhost/`. |
| **Gestión** (`/gestion/`) | Se usa desde el celular o la notebook, conectados a la misma red: álbumes, subir fotos y videos, ajustes de pantalla, Pase Rápido, mensajes y estado. |
| **Syncthing** | Sincroniza una carpeta del celular con el portarretrato, en casa o afuera, sin pasar por ningún servidor propio. Cada subcarpeta es un álbum; lo que se borra de un lado se borra del otro. |
| **Importador** (`agente/importar.php`) | Cada minuto (y tras cada subida) registra los archivos nuevos de la carpeta de álbumes, genera copias livianas y miniaturas, y recodifica los videos que no reproducen fluido. |

Carpetas: los originales viven en `sync/<Álbum>/` (lo que sincroniza Syncthing); el Visor muestra copias reducidas guardadas en `uploads/display/`. Lo que se borra desde Gestión queda 30 días en `sync-papelera/`.

## Instalación en el Debian

1. Copiar el proyecto a `/opt/portaretrato`.
2. Como root: `bash /opt/portaretrato/instalacion-portarretrato/linux/instalar-servidor.sh`
   Instala Apache, PHP, MariaDB, Syncthing y ffmpeg, crea la base, la clave de Gestión (`backend/config.local.php`) y el importador, y deja todo arrancando solo. Define `GESTION_PASSWORD=...` antes de correrlo para elegir la clave.
3. El kiosk (`linux/kiosk.sh`, lanzado desde `~/.xinitrc` del usuario `frame` con inicio de sesión automático) abre el Visor.

El portarretrato queda en la red como `http://portaretrato.local/`.

## Sincronizar el celular

En Gestión → **Sistema** hay un código QR con el ID de Syncthing y los pasos. Resumen: instalar Syncthing en el celular, agregar el dispositivo del portarretrato, aceptar el pedido en Gestión y elegir la carpeta del teléfono que se va a sincronizar.

## Diagnóstico

`bash /opt/portaretrato/instalacion-portarretrato/linux/diagnostico.sh`

## Estructura

```
index.php               Visor
gestion/                Gestión (login + app)
backend/                api.php, upload.php, sync_lib.php, config
agente/importar.php     Importador de la carpeta sincronizada
assets/                 css, js, íconos y librerías locales (sin CDN)
schema.sql              Esquema de la base de datos
instalacion-portarretrato/linux/   Scripts para el Debian
```
