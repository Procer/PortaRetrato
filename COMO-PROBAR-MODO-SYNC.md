# Cómo probar el Modo Sync localmente

> Cambios de la sesión 2026-08-27: Service Worker + `get_sync_manifest` para que
> cada foto/video viaje del hosting **una sola vez**, + horario de encendido del
> Visor con imagen de reposo, + tope de duración 60→120 s.
> **Todavía NO commiteado. NO probado en navegador.**

---

## 0. Requisito: contexto seguro para el Service Worker

El Service Worker **solo se registra** si abrís el Visor por:

- `http://localhost/...`  (localhost cuenta como seguro), **o**
- `https://portaretrato.test`  (en Laragon: clic derecho en la bandeja → `www` →
  `portaretrato` → *SSL*, o menú *Apache → SSL*; después reiniciá Apache).

Por **`http://portaretrato.test` (sin HTTPS) el SW NO arranca** y no vas a ver el
ahorro. El Visor igual funciona (cae al comportamiento viejo).

Para saber la URL local: en Laragon, botón *Web* o mirá los vhosts. Si el docroot
es `C:\laragon\www`, entonces `http://localhost/PortaRetrato/` sirve el Visor.

Antes de empezar: levantá **MySQL y Apache** en Laragon.

---

## 1. El Service Worker se registró

1. Abrí el Visor (con la URL del punto 0).
2. F12 → pestaña **Application** (Chrome/Edge) → **Service Workers**.
3. Tenés que ver `sw.js` con estado **activated and is running**.
   - Si dice algo de error o no aparece: revisá la consola. Causa típica =
     abriste por `portaretrato.test` sin HTTPS.

---

## 2. La media se está cacheando en disco

1. F12 → **Application** → **Cache Storage** → `pr-media-v1`.
2. A los ~4 segundos del primer cuadro, empiezan a aparecer entradas: una por
   cada foto/video del álbum activo (URL con `?v=...`).
3. Si el álbum tiene un video grande, tarda en aparecer (se baja entero una vez).

---

## 3. En la segunda carga NO se descarga nada del hosting

1. F12 → pestaña **Network**. Marcá *Disable cache* **DESACTIVADO** (queremos que
   use cache).
2. Recargá con **F5** (no Ctrl+F5).
3. Las filas de `uploads/*` deben decir **(ServiceWorker)** en la columna *Size*, o
   `0 B` transferido / *(disk cache)*.
4. La fila de `backend/api.php?action=get_sync_manifest` debe dar **304** cuando
   nada cambió (Size = 0 B). Se pide cada 10 min.

**Prueba de que reacciona a cambios:** en Gestión agregá o borrá una foto del
álbum activo. Dentro de 10 min (o recargá el Visor) el manifest cambia de
`version_hash`, se baja SOLO la foto nueva, y la borrada se saca del
`pr-media-v1`.

---

## 4. Horario de encendido + imagen de reposo

1. Gestión → **Ajustes del Slide**.
2. En **Horario de encendido del Visor**: pasá a **POR HORARIO**. Poné *Se apaga*
   un minuto adelante de la hora actual y *Enciende* un rato después. **Guardar**.
3. En el Visor, esperá a que llegue esa hora (máx ~30 s de chequeo + el minuto):
   - la reproducción se frena,
   - los widgets (reloj/clima) desaparecen,
   - aparece pantalla **negra** (todavía sin imagen de reposo).
4. Volvé a Gestión → Ajustes del Slide → **Imagen de reposo → Cargar imagen**.
   Elegí una foto. Debería verse el preview.
5. En el Visor (esperá el próximo chequeo o recargá): ahora en horario de reposo
   se ve **esa imagen a pantalla completa**, no negro.
6. Cambiá *Se apaga* para volver al horario normal → el Visor retoma el álbum
   solo desde la primera foto.
7. Probá **quitar** la imagen de reposo (botón rojo del tacho) → vuelve a negro.

Notas:
- El Modo Noche sigue siendo otra cosa (solo atenúa). En reposo, el Modo Noche
  no tapa la imagen de reposo.
- Si el Visor arranca **ya dentro** del horario de reposo, tiene que mostrar
  directamente la imagen (no debe quedar el overlay negro de inicio encima).

---

## 5. Duración por imagen

Gestión → Ajustes del Slide → el campo **Duración (segundos)** ahora acepta hasta
**120**. Poné 30 y guardá.

---

## Si algo quedó raro y querés empezar de cero

F12 → Application → **Service Workers → Unregister**, y **Cache Storage →** clic
derecho en `pr-media-v1` → *Delete*. Después recargá.

---

## Qué falta después de que esto ande

1. `git add` + commit + push (no se hizo).
2. Deploy a WNPower: subir los archivos + `sw.js` + `manifest.webmanifest`, correr
   `PARA-WNPOWER.sql`. **Confirmar que producción está en HTTPS** — sin eso el SW
   no se registra y no hay ahorro.
3. Opcional recomendado: bajar `VIDEO_MAX_BYTES` en `backend/upload.php` (hoy
   100 MB) y re-comprimir el `uploads/69e292ceb69ac.mp4` de 262 MB.

## Archivos tocados en esta sesión

`sw.js` (nuevo), `manifest.webmanifest` (nuevo), `index.php`,
`assets/js/viewer.js`, `assets/css/viewer.css`, `assets/js/gestion.js`,
`gestion/index.php`, `backend/api.php`, `backend/upload.php`, `.htaccess`,
`PARA-WNPOWER.sql`.
