# Análisis: consumo de ancho de banda del PortaRetrato

**Fecha:** 2026-09-03
**Estado:** investigación / propuesta. No se programó nada todavía.
**Objetivo:** que el portaretrato funcione sin volver a consumir ~300 GB/mes, con una
solución más simple que el Service Worker actual, manteniendo intactos los ajustes de
Modo Noche y "Horario de encendido del Visor".

---

## 1. Resumen ejecutivo (TL;DR)

El Service Worker fue una solución demasiado compleja y frágil para este caso. La
investigación muestra que el problema real se puede resolver **con configuración, casi
sin código**:

| # | Acción | Dónde | Esfuerzo | Impacto |
|---|--------|-------|----------|---------|
| 1 | Servir `/uploads/*` (incluidos **videos**) como estáticos con caché larga (`immutable`) | WNPower (ticket) | bajo | **alto** |
| 2 | Whitelistear la IP del portaretrato en la seguridad (Imunify360/BitNinja) | WNPower (ticket / cPanel) | bajo | alto |
| 3 | Agrandar el caché de disco de Chrome: `--disk-cache-size` en el acceso directo del kiosco | La PC del portaretrato | mínimo | **alto** |
| 4 | Reemplazar `sw.js` por una versión que se autodesregistra (quitar el Service Worker) | 1 archivo, 5 líneas | mínimo | (limpieza) |

Con eso, cada foto y cada video se descargan **una sola vez** y quedan en el caché de
disco de Chrome (hay 800 GB libres). No hace falta agente de sincronización, ni proxy, ni
reescribir el Visor. **Los ajustes de la imagen (Modo Noche 23–07, Horario de encendido
07–23) no se tocan y siguen funcionando igual** — es más, el "Horario de encendido" ya
ayuda: 8 h por día el Visor muestra una imagen fija y no pide nada.

Si preferís **cero dependencia del hosting**, la alternativa es la **Opción B** (sincronizar
una carpeta local por FTP), más eficiente en bytes pero con más trabajo de puesta a punto.

---

## 2. Qué encontramos en la investigación

Pruebas hechas con `curl` contra `https://zaratesystemgroup.com.ar/PortaRetrato/`:

### 2.1 Hay un filtro de seguridad JS ("One moment, please…")

Todo lo que **no** sea un archivo estático conocido devuelve una página HTML de desafío
(Imunify360 / BitNinja, típico de WNPower), no el contenido real:

| Recurso | Respuesta |
|---|---|
| `/PortaRetrato/` (el HTML del Visor) | **HTML de desafío** (`text/html`, `no-cache`) |
| `backend/api.php?action=get_sync_manifest` | **HTML de desafío** (¡no el JSON!) |
| `assets/js/viewer.js`, `assets/css/viewer.css` | OK, servido por nginx |
| `uploads/<foto>.jpg` (existente) | OK, `image/jpeg`, `Cache-Control: max-age=604800` |
| `uploads/<algo>.mp4` | **HTML de desafío** (a confirmar con un video real) |
| `uploads/` (listado) | **HTML de desafío** |

Un navegador real pasa el desafío una vez y recibe una **cookie de clearance**. El
problema aparece cuando esa cookie **vence** o cambia la IP de salida (conexiones
residenciales que renuevan IP): a partir de ahí el servidor responde HTML en lugar de la
foto / del JSON, el `<img>` falla, y el Visor reintenta en cada vuelta → consumo que no
baja nunca.

### 2.2 El `.htaccess` NO se está aplicando

El front es **nginx** (`Server: nginx`), no Apache. Las reglas de caché e `immutable` que
tiene el `.htaccess` del proyecto **se ignoran**. nginx aplica su propia política:

- `.js`, `.css`, `.jpg`, `.png`, `.webp` → `Cache-Control: max-age=604800` (7 días).
- `sw.js` → también `max-age=604800` (¡mal! un Service Worker no debería cachearse).
- **Video (`.mp4`, `.webm`)** → parece **no** estar en la lista de estáticos de nginx →
  cae en la app → sale con `no-cache` y/o el desafío → **el navegador no lo puede
  cachear → se re-descarga en cada vuelta**. *(Confirmar con la URL de un video real del
  álbum, sacada desde Gestión.)*

### 2.3 El caché del navegador no alcanzaba

El caché de disco de Chrome por defecto es chico (unos cientos de MB a ~1–2 GB, compartido
con todos los sitios). El álbum (fotos + **videos**) no entra entero → Chrome expulsa y
vuelve a descargar. Es el mismo "thrashing" que después vimos en el caché del Service
Worker, que quedó topado en ~140 archivos / ~451 MB.

Dato del panel Diagnóstico: 140 de 362 archivos ocupaban 451 MB → el álbum completo pesa
**~1,2 GB** aprox. (con videos incluidos). Entra de sobra en un caché de disco agrandado.

### 2.4 Lo que NO es el problema

- **No es el disco del equipo**: tiene 800 GB libres.
- **No es incógnito**: Chrome se lanza limpio (`--kiosk` + URL, sin `--incognito` ni
  `--guest`).
- **No es HTTPS**: ya está en HTTPS y el Service Worker llegó a registrarse.

---

## 3. Por qué el Service Worker no fue la solución adecuada

| Problema | Detalle |
|---|---|
| Pelea con el filtro de seguridad | Si la respuesta es la página "One moment please", el SW la cacheaba como si fuera la foto → foto rota para siempre. Ya se blindó, pero es sofisticación para tapar un problema que no debería existir. |
| Bug de subcarpeta | El sitio está en `/PortaRetrato/`; la lógica de limpieza (`PRUNE_MEDIA`) borraba todo lo recién bajado por un error de rutas. Ya corregido, pero muestra lo delicado que es. |
| Cupo de almacenamiento | El "Cache Storage" del SW es *best-effort*: Chrome le da unos cientos de MB y lo puede expulsar. `navigator.storage.persist()` no se concede solo en un sitio nuevo. |
| Propagación de cambios | nginx sirve `sw.js` con 7 días de caché; actualizar el SW en un kiosco que nunca recarga puede tardar hasta 24 h o requerir intervención. |
| Difícil de diagnosticar | Hizo falta un panel de métricas entero solo para saber si estaba funcionando. |

El Service Worker tiene sentido para apps web offline complejas. Para "una PC con Chrome
que muestra fotos", el caché HTTP de siempre + headers correctos es más que suficiente y
lo entiende cualquiera.

---

## 4. Opciones analizadas

### Opción A — Caché HTTP de Chrome agrandado + headers correctos  ⭐ recomendada

**Idea:** dejar que Chrome haga lo que ya sabe hacer — cachear en disco — pero con
espacio suficiente y con el servidor mandando los headers adecuados. Sin Service Worker.

**Cambios**

1. **WNPower (ticket):**
   - Servir `location ~* ^/PortaRetrato/uploads/ { expires 365d; add_header Cache-Control "public, immutable"; }` — o equivalente — para **todas** las extensiones de media, **videos incluidos**.
   - Servir `PortaRetrato/sw.js` con `Cache-Control: no-cache` (para poder retirarlo/limpiarlo).
   - **Whitelistear la IP pública del portaretrato** en Imunify360/BitNinja para que nunca reciba el desafío JS. (IP: abrir `https://api.ipify.org` en el equipo.)
2. **PC del portaretrato:** editar el acceso directo del kiosco:
   ```
   "C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk ^
     --disk-cache-size=17179869184 ^
     --disk-cache-dir="C:\PortaRetratoCache" ^
     https://zaratesystemgroup.com.ar/PortaRetrato
   ```
   (`17179869184` = 16 GB; el álbum pesa ~1,2 GB, sobra muchísimo.)
3. **Código (mínimo):** reemplazar `sw.js` por un "tombstone" que se autodesregistra, así
   los equipos que ya tienen el SV viejo se limpian solos. ~5 líneas.

**Cómo queda el funcionamiento**

- Chrome baja cada foto/video la primera vez, lo guarda en `C:\PortaRetratoCache`.
- En las vueltas siguientes lo sirve de disco: **0 bytes al hosting**.
- El caché de disco de Chrome **sobrevive a cerrar el navegador y a reiniciar la PC**.
- Solo se re-descarga un archivo si: cambia en el servidor, o se borra el perfil/caché de
  Chrome.
- El polling del manifest (`get_sync_manifest`) sigue, pero es JSON de pocos KB (y con la
  IP whitelisteada ya no hay riesgo de que devuelva la página de seguridad).

**Pros**

- Casi sin código. Nada de agentes, servicios ni proxies.
- Es la técnica estándar de cartelería digital / kioscos.
- Fácil de entender y de mantener.
- Los ajustes de Modo Noche y Horario de encendido no se tocan.

**Contras / riesgos**

- Depende de que WNPower configure nginx (headers + whitelist). Sin eso, el video sigue
  siendo un agujero.
- Si la IP del portaretrato es dinámica y cambia seguido, cada cambio dispara un desafío
  → recarga de la página (unos KB de HTML, no re-descarga las fotos porque siguen en el
  caché de disco). El whitelist por IP lo resuelve; si la IP cambia, hay que whitelistear
  el rango o pedir IP fija.
- El caché de Chrome no es tan "para siempre" como una carpeta propia: si alguien limpia
  datos de navegación en el equipo, se pierde y se vuelve a bajar una vez.

---

### Opción B — Sincronizar una carpeta local por FTP + Visor desde local

**Idea:** una tarea programada de Windows baja el álbum a una carpeta local y Chrome
muestra desde ahí. El hosting solo se toca para bajar lo nuevo.

**Cambios**

1. **Transporte:** FTP/SFTP de WNPower (cPanel lo da). **El FTP no pasa por el filtro web**,
   así que esquiva el desafío por completo.
2. **Agente de sync:** [`rclone`](https://rclone.org) (un `.exe` portable) en Tarea
   Programada cada 30 min:
   ```
   rclone sync ftp:public_html/PortaRetrato/uploads  C:\PortaRetrato\media  --fast-list
   ```
   `sync` baja solo lo nuevo/cambiado y borra local lo que ya no está. Consumo = exactamente
   esos bytes, nunca más.
3. **Visor desde local:** dos variantes:
   - **a)** Un mini server estático local (p. ej. el mismo nginx/Apache de la PC, o
     `http-server` de Node) sirviendo `C:\PortaRetrato\` en `http://localhost:8080`, y el
     Visor pidiendo el manifest a `https://…/backend/api.php` pero las **fotos a
     `http://localhost:8080/media/…`**. Requiere un pequeño cambio en `viewer.js` para
     prefijar las rutas de media.
   - **b)** El Visor entero servido local y solo el manifest/mensajes/clima salen a
     internet.

**Pros**

- El más eficiente en bytes: baja **solo** lo nuevo, garantizado.
- Funciona 100 % offline (salvo clima y mensajes familiares).
- Independiente del filtro de seguridad y de los headers de nginx.
- El caché no se puede "perder" por limpiar el navegador.

**Contras**

- Es el que más trabajo de puesta a punto tiene: instalar rclone, crear la tarea, guardar
  credenciales FTP en cada equipo, montar el server local, y tocar `viewer.js` para las
  rutas.
- Una pieza más que mantener (la tarea programada) en cada portaretrato.

---

### Opción C — Proxy de caché local en la PC

Un nginx/Squid chico corriendo en la PC como proxy; Chrome sale por `127.0.0.1:8080`; el
proxy cachea `/uploads/*` en disco (sin límite práctico).

- **Pros:** transparente para la app; control total del caché; sobrevive a todo.
- **Contras:** instalar y mantener un servicio en cada equipo; hay que configurarlo para
  cachear **solo** respuestas `image/*` y `video/*` (si no, cachea la página de seguridad);
  más complejo que la Opción A sin ser tan sólido como la B.

---

### Opción D — Bundle ZIP por álbum

El servidor arma `album_<hash>.zip` con el álbum activo; la PC lo baja cuando cambia el
hash, lo descomprime local, Chrome muestra local.

- **Pros:** 1 sola descarga por cambio de álbum; simple de razonar.
- **Contras:** generar el zip en shared hosting con videos puede pegarle a los límites de
  memoria/tiempo de PHP; la PC necesita descomprimir (script); el Visor tiene que leer de
  local; el zip pasa por el desafío (1 request, reintentable).

---

### Opción E — App nativa de slideshow (descartada)

Windows tiene slideshow nativo; con una carpeta sincronizada alcanzaría para mostrar
fotos. **Se descarta** porque se pierden el reloj, el clima, los mensajes familiares, el
Pase Rápido y la botonera arcade — que son la razón de ser de este proyecto.

---

## 5. Recomendación

1. **Hacer primero la Opción A.** Es config, se prueba en una tarde y probablemente
   resuelve el 90 % solo con:
   - el ticket a WNPower (headers de media + whitelist de IP), y
   - el flag `--disk-cache-size` en el kiosco.
2. **Medir 3–4 días** en cPanel → Ancho de banda.
3. Si el consumo **no** baja lo esperado (típicamente por video mal servido o IP
   dinámica que se re-desafía), evaluar pasar a la **Opción B** (FTP + carpeta local),
   que quita del medio tanto el filtro como los headers de nginx.

El Service Worker se retira en ambos casos (tombstone `sw.js`). El panel "Diagnóstico"
puede quedar: sigue siendo útil para ver por equipo cuánto pesa el caché y hace cuánto
reportó, aunque ya no mida "cache-miss" del SW.

---

## 6. Qué NO cambia

- **Modo Noche (23:00–07:00)** y **Horario de encendido del Visor "POR HORARIO"
  (07:00–23:00)**: son lógica de `viewer.js` en el equipo (`checkNightMode`,
  `checkVisorSchedule`). No dependen del mecanismo de caché. Se mantienen tal cual la
  imagen.
- El "Horario de encendido" además **ayuda**: fuera de 07–23 el Visor frena la
  reproducción y muestra la imagen de reposo → 8 h por día sin pedir media.
- Toda la interfaz del Visor (reloj, clima, mensajes, Pase Rápido, botonera) sigue igual.

---

## 7. Cómo confirmar que funcionó

| Señal | Dónde | Esperado |
|---|---|---|
| Consumo diario | cPanel → Ancho de banda | Cae a **decenas de MB/día** después del primer día |
| Primer día tras el cambio | cPanel | Un pico (baja el álbum entero 1 vez, ~1–2 GB) y después plano |
| Caché de Chrome | `chrome://version` → "Perfil"; o el tamaño de `C:\PortaRetratoCache` | Crece hasta ~1,2 GB y se estabiliza |
| Requests repetidos a `/uploads/` | cPanel → Accesos sin procesar, filtrando por la IP del equipo | Tras la primera vuelta: **ninguno** (solo `get_sync_manifest`) |
| Video | Accesos sin procesar | El `.mp4` aparece **una vez**, no en cada vuelta |

---

## 8. Preguntas abiertas / a verificar

1. **¿El álbum actual tiene videos?** Si no tiene, el problema es casi todo "caché de
   Chrome chico" y la Opción A punto 3 (el flag) sola ya hace la diferencia.
2. **Confirmar headers de un video real** de producción (URL sacada desde Gestión) — para
   saber si nginx lo sirve estático o cae en la app.
3. **¿La IP del portaretrato es fija o dinámica?** Define si alcanza con whitelistear una
   IP o hay que pedir rango / IP fija a WNPower.
4. **¿WNPower permite tocar la config de nginx** por ticket, o solo `.htaccess`? Si es solo
   Apache/`.htaccess`, hay que ver por qué nginx lo ignora (puede ser un caché de página
   tipo "LiteSpeed"/"Engintron" que haya que desactivar para este directorio).
