# Flota de portarretratos: panel, pendrive y actualizaciones

## Cómo encaja todo

```
 Tu PC ──git push──▶ GitHub (main)  ──[promover]──▶ GitHub (stable)
                          │                              │
        tu notebook (canal main)              equipos de tus amigos (canal stable)
        se actualiza cada noche               se actualizan cada noche
                          ▲                              ▲
                          └────── cada 10 min ───▶  PANEL (VPS)  ◀── vos, desde el navegador
                                  "estoy vivo + mi estado"        funciones, canal, acciones
```

- **Los equipos llaman al panel** (HTTPS saliente). No hay que abrir nada en el router de tus amigos.
- **Funciones**: en el panel activás/desactivás funciones por equipo (clima, mensajes, Pase Rápido, horario, modo noche, sincronización). Llegan al equipo en ≤10 min; el dueño no puede cambiarlas.
- **Actualización**: cada noche (~03:30) cada equipo baja de GitHub la rama de su canal. Si tras actualizar el Visor no responde, **vuelve solo a la versión anterior** y el panel muestra "update falló".
- **Dos canales**: `main` = lo último que programás (tu notebook). `stable` = lo que vos promovés (equipos de clientes). Un error en `main` no les llega.

## 1. Poner el panel en el VPS (una vez)

1. En el DNS de tu dominio: registro **A** `panel.tudominio.com` → IP del VPS. En el firewall del VPS: puertos **80 y 443** abiertos.
2. En el VPS (Debian/Ubuntu), como root:
   ```
   apt-get install -y git
   git clone https://github.com/Procer/PortaRetrato.git /opt/portaretrato-panel
   bash /opt/portaretrato-panel/panel/install-panel.sh panel.tudominio.com 'UnaClaveLarga'
   ```
   Al final imprime el **código de alta** (`PANEL_ENROLL`). También está en el panel → Ajustes.
3. Entrá a `https://panel.tudominio.com/` con la clave de administrador.
4. (Opcional) En Ajustes cargá un **token de GitHub** (fine-grained, solo este repositorio, *Contents: Read and write*) para usar el botón **Promover main → stable**. Sin token promovés con `git push origin main:stable`.

El panel se actualiza solo cada noche desde GitHub. Datos en `/var/lib/portaretrato-panel/panel.db` (SQLite): para respaldarlo alcanza con copiar esa carpeta.

## 2. Preparar el pendrive

Con el pendrive Ventoy conectado (con la ISO de Debian en su raíz), desde la carpeta del proyecto en tu PC:

```
powershell -ExecutionPolicy Bypass -File instalacion-portarretrato\preparar-pendrive.ps1 -Panel https://panel.tudominio.com -Enroll <codigo-de-alta>
```

Se puede volver a correr cuando cambie el panel o el código. Sin `-Panel` los equipos se instalan igual, pero no quedan registrados.

## 3. Instalar una notebook

1. Notebook por **cable** al router (necesita internet). Arrancar desde el pendrive. **Se borra todo el disco interno sin preguntar.**
2. Se instala Debian y reinicia. En el primer arranque descarga el proyecto e instala todo (10–20 min, se ve el avance en pantalla) y reinicia sola. Si falla (por ejemplo, sin internet) **reintenta en el siguiente arranque**.
3. El equipo se registra solo en el panel y aparece en **Equipos**.
4. Probar como lo vería el dueño (ver `GUIA-NUEVO-EQUIPO.md`): sin cable, WiFi propio, asistente de bienvenida.
5. Para entregar: `sudo bash /opt/portaretrato/instalacion-portarretrato/linux/preparar-entrega.sh` y apagar.

## 4. Tu notebook de siempre = "tu versión"

Ponela en canal **main** y que se actualice sola con lo último que subas:

```
sudo bash /opt/portaretrato/instalacion-portarretrato/linux/instalar-agente.sh https://panel.tudominio.com <codigo-de-alta> main
```

(Si el proyecto no es un clon de git, primero convertilo: `git init && git remote add origin https://github.com/Procer/PortaRetrato.git && git fetch origin main && git reset --hard origin/main`.)

## 5. Tu flujo de trabajo diario

1. Programás en tu PC → `git push` a `main`. Tu notebook lo recibe esa noche (o ya con **Actualizar ahora** en el panel).
2. Lo probás en tu notebook. Si está bien: panel → **Promover main → stable**.
3. Esa noche todos los equipos de clientes lo instalan. En el panel ves quién quedó al día, quién falló y quién está sin conexión.
4. ¿Algo urgente? **Actualizar todos ahora**, o entrá al equipo y **Actualizar ahora** / **Reabrir el Visor** / **Reiniciar**.

## 6. Agregar una función nueva activable

1. Sumala a `backend/features.json` (clave, nombre, descripción). Aparece sola en el panel.
2. En el Visor / Gestión, respetá el flag: en el servidor `featOn($pdo, 'clave')`; en el Visor `FEAT.clave`; en Gestión `FEAT.clave` (ocultar pestaña o sección en `TAB_FEAT` / `SECTION_FEAT`).
3. Cambios de base: en `schema.sql` (se aplica en cada actualización). Cambios del sistema operativo: `instalacion-portarretrato/linux/post-update.sh` (se ejecuta tras cada actualización con la versión anterior y la nueva como argumentos).

## 7. Qué mirar si algo falla

| Síntoma | Dónde |
|---|---|
| Equipo "sin conexión" en el panel | ¿Apagado o sin internet? Al volver avisa solo. |
| "update falló" | En el equipo: `/var/lib/portaretrato/actualizar.log`; ya volvió a la versión anterior. |
| Equipo no aparece tras instalar | `journalctl -u portaretrato-agente`; revisar `PANEL_URL`/código en `/var/lib/portaretrato/panel.json`. |
| Instalación del primer arranque | `/var/log/portaretrato-firstboot.log` |

## 8. Seguridad

- Cada equipo tiene un token propio (se guarda con hash en el panel). Si perdés un equipo: **Eliminar equipo** en el panel y deja de recibir funciones.
- El código de alta solo sirve para registrar equipos nuevos; rotalo en Ajustes si se filtra.
- El pendrive lleva claves (usuario `frame` y código de alta): guardalo como una llave.
- El panel solo conoce estado técnico (versión, disco, WiFi, cantidad de fotos, ciudad). **No ve ni recibe fotos ni mensajes.**
- Aviso a tus clientes: el equipo informa su estado al panel. Conviene decírselo.
