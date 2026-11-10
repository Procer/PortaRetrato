# Plan para la próxima sesión: poner en marcha la flota

Estado de partida (commit `773f28a` en GitHub, rama `main`): panel, agente, actualizador nocturno, funciones activables, pendrive y guías **escritos pero sin probar en equipos reales**. Falta: VPS con el panel, probar con tu notebook, instalar la notebook nueva y entregarla.

Tiempo estimado: 3–4 horas (la mayor parte es esperar instalaciones).

---

## A. Lo que tenés que traer resuelto ANTES de la sesión

| # | Qué | Cómo / detalle |
|---|---|---|
| 1 | **VPS de DonWeb** encendido | Debian 12/13 o Ubuntu 22.04/24.04. Anotá la **IP pública** y tené el **acceso SSH como root** (usuario y clave). Con 1 GB de RAM sobra. |
| 2 | **Subdominio para el panel** | En el DNS de tu dominio (el de `zaratesystemgroup.com.ar`): registro **A** `panel` → IP del VPS. Tarda de minutos a unas horas en propagar. Verificá con `nslookup panel.zaratesystemgroup.com.ar`. |
| 3 | **Firewall del VPS** | Puertos **80 y 443** abiertos (en el panel de DonWeb y/o `ufw allow 80,443/tcp`). El 22 solo para vos. |
| 4 | **Clave del administrador del panel** | Elegí una larga (mín. 8) y anotala. Se la voy a pasar al instalador. |
| 5 | **Ambas notebooks y el pendrive** | La de siempre (verificá su IP: `ping portaretrato.local`; la última fue 192.168.0.11) y la nueva con su cargador. **Cable de red** para la nueva (necesita internet en la instalación). El pendrive Ventoy (`D:`) ya tiene el preseed nuevo. |
| 6 | **Token de GitHub (opcional)** | Para el botón "Promover main → stable". github.com → Settings → Developer settings → Fine-grained tokens → solo el repo `Procer/PortaRetrato`, permiso *Contents: Read and write*. Sin token se promueve con `git push origin main:stable`. |
| 7 | **Decisión** | ¿Autorizás convertir tu notebook de siempre en clon de git y dejarla en canal `main` (tu versión de pruebas)? En la sesión confirmalo al inicio (la última vez el sistema bloqueó esa acción hasta que lo decidas vos). |

---

## B. Orden de la sesión

### Paso 0 — Verificar el punto de partida (10 min)
1. `git log --oneline -1` debe mostrar `773f28a` o posterior; `git status` limpio.
2. Pendrive: existen `D:\ventoy\script\preseed.cfg`, `D:\ventoy\ventoy.json` y la ISO `debian-13.7.0-amd64-netinst.iso`.
3. Tu notebook de siempre responde por SSH (`ssh root@<ip>`).

### Paso 1 — Instalar el panel en el VPS (20 min)
1. `ssh root@<IP-VPS>`
2. ```
   apt-get update && apt-get install -y git
   git clone https://github.com/Procer/PortaRetrato.git /opt/portaretrato-panel
   bash /opt/portaretrato-panel/panel/install-panel.sh panel.zaratesystemgroup.com.ar 'CLAVE-ADMIN'
   ```
3. **Anotar el código de alta** que imprime al final (`PANEL_ENROLL`). También está en el panel → Ajustes.
4. Verificar desde tu PC:
   - `https://panel.zaratesystemgroup.com.ar/` abre con candado y pide clave.
   - Entrás; la lista de equipos está vacía; Ajustes muestra el código.
5. Si Caddy no obtiene el certificado: casi siempre es DNS sin propagar o puerto 80/443 cerrado → `journalctl -u caddy -n 50`.
6. (Opcional) Ajustes → pegar el token de GitHub.

### Paso 2 — Registrar tu notebook de siempre como "tu versión" (30 min)
*(Solo si lo autorizaste en el punto A.7.)*
1. Conectar por SSH a la notebook y convertir el proyecto en clon (conserva config, fotos y base: están ignorados por git):
   ```
   cd /opt/portaretrato
   runuser -u frame -- git init -b main
   runuser -u frame -- git remote add origin https://github.com/Procer/PortaRetrato.git
   runuser -u frame -- git fetch origin main
   runuser -u frame -- git reset --hard FETCH_HEAD
   ```
2. Instalar el agente en canal main:
   ```
   bash instalacion-portarretrato/linux/instalar-agente.sh https://panel.zaratesystemgroup.com.ar <CODIGO-ALTA> main
   install -m 755 instalacion-portarretrato/linux/pr-admin /usr/local/bin/pr-admin
   php agente/panel_agent.php
   ```
3. Verificar: el equipo aparece en el panel (nombre `portaretrato`), canal `main`, "En línea". En Gestión → Sistema aparece la sección "Mi portarretrato".
4. **Pruebas de funciones** (panel → el equipo → desmarcar → Guardar; el cambio llega en ≤10 min o forzalo con `php agente/panel_agent.php` en el equipo):
   - Desmarcar **Clima** → desaparece el widget del Visor y la sección Clima en Pantalla.
   - Desmarcar **Mensajes** → desaparecen el ícono de notas y la pestaña Mensajes; `/hija/` responde "desactivada".
   - Desmarcar **Pase Rápido**, **Horario**, **Modo noche**, **Sincronización** → pestaña/sección oculta.
   - Volver a marcar todo.
5. **Pruebas de comandos**: "Reabrir el Visor" (la pantalla parpadea y vuelve), "Actualizar ahora" (queda en historial; debe decir "Al día").
6. Anotar en la memoria cualquier error (usar `journalctl -u portaretrato-agente -n 30`).

### Paso 3 — Probar la actualización nocturna y la vuelta atrás (20 min)
1. En tu PC: cambio trivial (por ejemplo un comentario en `README.md`) → commit → `git push`.
2. Panel → equipo → **Actualizar ahora**. En ≤10 min: la versión del panel cambia al commit nuevo; el historial muestra "Versión X → Y".
3. **Probar el rollback** (importante): subir un commit que rompa a propósito `index.php` (por ejemplo un error de sintaxis PHP en `backend/api.php`), **Actualizar ahora**, y comprobar que el equipo vuelve solo a la versión anterior, que el panel marca "update falló" y que el Visor sigue funcionando. Luego revertir ese commit en GitHub (`git revert` + push).
4. Verificar que el timer nocturno existe: `systemctl list-timers 'portaretrato-*'`.

### Paso 4 — Preparar el pendrive con el panel (5 min)
En tu PC, con el pendrive conectado:
```
powershell -ExecutionPolicy Bypass -File instalacion-portarretrato\preparar-pendrive.ps1 -Panel https://panel.zaratesystemgroup.com.ar -Enroll <CODIGO-ALTA> -Canal main
```
Usar `-Canal main` **solo para esta primera prueba** (todavía no existe `stable`); después de promover se vuelve a correr sin `-Canal` para clientes.

### Paso 5 — Instalar la notebook nueva con el pendrive (30–40 min, casi todo espera)
1. Cable de red al router. Antes de empezar, en la notebook: **BIOS/UEFI** → desactivar *Secure Boot* si no arranca desde USB; elegir arrancar desde USB (F12 / F2 / Esc).
2. En Ventoy elegir la ISO de Debian. **Se borra todo el disco interno sin preguntar.** Dejar que termine solo.
3. Primer reinicio: sacar el pendrive. Se ve el avance de `pr-firstboot` en pantalla (10–20 min) y reinicia sola.
4. Si queda trabado o falla: `journalctl -u portaretrato-firstboot` / `/var/log/portaretrato-firstboot.log`. Se reintenta al reiniciar.
5. Verificar: aparece en el panel como equipo nuevo; el Visor muestra la pantalla de **bienvenida** (sin red WiFi aún, por cable) con la clave inicial.
6. Chequeo de hardware WiFi (en la notebook, por SSH `ssh frame@<ip>`):
   ```
   nmcli dev                                   # debe listar un dispositivo "wifi"
   iw list | grep -A8 "Supported interface modes"   # debe incluir "AP"
   ```
   Si no aparece WiFi: faltan drivers (`lspci -nnk | grep -A3 -i network`) → instalar el firmware correspondiente o usar un adaptador USB WiFi/cable.

### Paso 6 — Probar la experiencia del dueño (30 min, la parte menos probada)
1. **Desconectar el cable**, reiniciar la notebook.
2. A los ~45–60 s la pantalla debe mostrar **"Conectemos tu portarretrato a internet"** con nombre `Portaretrato-XXXX`, clave y QR.
3. Con el celular: escanear el QR (o conectarse a mano). **Debe abrirse sola** la página "Conectar al WiFi"; si no, abrir `http://10.42.0.1/conectar/`.
4. Elegir tu WiFi, poner la clave, **Conectar**. La pantalla debe pasar por "Conectando…" y luego "¡Bienvenido! Ya hay internet" con QR y clave inicial.
5. **Probar el error**: repetir poniendo una clave mal → la pantalla debe mostrar el error y volver a abrir el WiFi propio.
6. Con el celular en tu WiFi: abrir la dirección de la pantalla, entrar con la clave inicial y completar el **asistente** (clave, ciudad, nombre, orientación, zona horaria). Debe terminar en la pestaña **Ayuda**.
7. Revisar que Sistema → "Mi portarretrato" funciona: cambiar orientación, zona horaria, cambiar WiFi, reiniciar.
8. Anotar todo lo que falle: este flujo es el más probable que necesite ajustes.

### Paso 7 — Promover a `stable` y dejar los clientes en ese canal (15 min)
1. Con todo lo anterior validado: panel → **Promover main → stable** (o `git push origin main:stable`).
2. Verificar en el panel que `stable` = `main`.
3. Cambiar el canal de la notebook nueva a **stable** (panel → equipo → Canal → Guardar) y comprobar que sigue funcionando tras **Actualizar ahora**.
4. Volver a preparar el pendrive **sin** `-Canal main` (por defecto `stable`) para los próximos equipos.

### Paso 8 — Dejar la notebook lista para entregar (10 min)
1. En la notebook: `sudo bash /opt/portaretrato/instalacion-portarretrato/linux/preparar-entrega.sh` (o con `--borrar-fotos`). Confirmar con `SI`.
2. `sudo poweroff`. Guardar con su cargador.
3. Panel → equipo → ponerle **nombre y dueño** (ej. "Casa de Marcos") y revisar las funciones que tendrá activas.
4. Opcional: imprimir `GUIA-NUEVO-EQUIPO.md` sección 3 (lo que vive el dueño) y dársela.

### Paso 9 — Cierre (10 min)
1. Commit/push de cualquier ajuste hecho en la sesión.
2. Guardar en memoria: URL del panel, estado de cada prueba (OK / falló), pendientes.
3. Respaldar la base del panel: `scp -r root@<VPS>:/var/lib/portaretrato-panel ./respaldo-panel` (guardar fuera del repo).
4. Avisar a tu amigo que el equipo informa su estado técnico al panel (ver GUIA-PANEL §8).

---

## C. Qué puede fallar y qué hacer

| Si pasa esto | Causa probable | Qué hacer |
|---|---|---|
| Caddy no emite el certificado | DNS sin propagar / 80-443 cerrados | `dig panel.…`, abrir puertos, `systemctl restart caddy` |
| El equipo no aparece en el panel | URL o código mal / sin internet | `cat /var/lib/portaretrato/panel.json`, `php agente/panel_agent.php` (muestra el error) |
| `git reset` en la notebook se queja de archivos | Archivos sin versionar que chocan | Mirar el mensaje; el `reset --hard` los pisa, pero respaldar `backend/config.local.php` antes por las dudas |
| El primer arranque no instala nada | `in-target systemctl enable` no tomó / sin internet | `systemctl status portaretrato-firstboot`; correr `pr-firstboot` a mano |
| No aparece el WiFi propio | Placa sin modo AP o sin drivers | Ver Paso 5.6; alternativa: adaptador USB WiFi o cable |
| El celular no abre solo la página | Algunos Android/iOS no hacen la detección | Abrir `http://10.42.0.1/conectar/` a mano |
| Tras actualizar, el Visor queda en negro | Cambio con error | El rollback debe revertirlo solo; si no, `sudo pr-actualizar` o `git -C /opt/portaretrato reset --hard <commit-anterior>` |
| `portaretrato.local` no resuelve | mDNS no disponible | Usar la IP que muestra la pantalla/Gestión |

## D. Cosas que están escritas pero NO probadas todavía

- `install-panel.sh` (Caddy + php-fpm en el VPS).
- Instalación desde el pendrive con `pr-firstboot` (el servicio de primer arranque, sin servicios dentro del instalador).
- WiFi propio (`pr-red.sh`), página `/conectar/`, cambio de red, reintento si falla la clave.
- `pr-actualizar` (actualización y vuelta atrás) y el agente en un equipo real.
- `preparar-entrega.sh`.

El panel (alta, aviso, funciones, comandos, historial, seguridad) sí se probó en tu PC con peticiones simuladas de un equipo.

## E. Después de esta sesión (ideas)

1. Backup automático del panel y de los equipos a un disco USB.
2. PIN opcional en `/hija/`.
3. Control físico de 3 botones (ESP32 o teclado hackeado).
4. Captura de pantalla remota desde el panel para soporte.
5. Acceso remoto (Tailscale) solo si el panel no alcanza para dar soporte.
