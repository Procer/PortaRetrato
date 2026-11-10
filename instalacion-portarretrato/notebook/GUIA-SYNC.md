# Fotos del celular → portarretrato (Syncthing)

**Idea:** una carpeta del celular se sincroniza sola con la notebook. Cada subcarpeta es un álbum.
Lo que borrás de un lado se borra del otro.

## En la notebook (una vez)
1. Laragon: *Menú → Preferencias → General*: marcar **Ejecutar al iniciar Windows** y **Iniciar todo automáticamente**.
2. Inicio de sesión automático (para que arranque solo tras un corte de luz): `Win+R` → `netplwiz` → destildar *Los usuarios deben escribir su nombre y contraseña*.
3. PowerShell: `powershell -ExecutionPolicy Bypass -File instalacion-portarretrato\notebook\instalar-notebook.ps1`
4. Base de datos (si falta): `mysql portaretrato < migrate_carpetas.sql` y `php agente/migrar_a_carpetas.php` (pasa lo ya cargado a carpetas).
5. Abrir http://127.0.0.1:8384 → *Acciones → Configuración → GUI*: poner usuario y contraseña.
6. *Agregar carpeta*: Etiqueta `Fotos`, **Ruta = la carpeta `sync` del proyecto**, tipo *Enviar y recibir*,
   pestaña *Control de versiones* → **Papelera** (días: 30).

## En el celular Android (una vez)
1. Instalar **Syncthing-Fork** (F-Droid / Play Store) y abrirlo.
2. En el celular: *Mostrar ID* → copiarlo. En la notebook: *Agregar dispositivo* con ese ID. Aceptar el pedido en el celular.
3. Compartir la carpeta `Fotos` con el celular; en el celular elegir una carpeta (ej. `Download/PortaRetrato`), tipo *Enviar y recibir*.
4. Fuera de casa funciona igual (usa los servidores de retransmisión de Syncthing, cifrado). En el celular activar *Ejecutar en datos móviles* si querés subir sin WiFi.

## Uso diario
- Crear una carpeta en `PortaRetrato` del celular = álbum nuevo. Meter fotos/videos ahí = aparecen en 1–2 minutos.
- Borrar una foto de esa carpeta (o desde Gestión) = desaparece del portarretrato y del otro lado.
- Formatos: JPG, PNG, WebP, GIF, MP4, WebM. (HEIC no: en la cámara elegir JPG.)
- Lo borrado desde Gestión va a `sync-papelera/` 30 días; lo borrado desde el celular queda en la papelera de Syncthing (`.stversions`).

## Si algo no aparece
`agente\importar.log` muestra qué hizo el importador y qué archivos no pudo procesar.
