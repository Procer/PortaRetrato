# Instalación: hosting liviano + espejo en la PC del portarretrato

**Idea:** el hosting (WNPower) solo guarda las fotos y recibe tus subidas desde Gestión.
La PC del portarretrato baja cada foto/video **una sola vez** y el Visor lo lee del disco.
Resultado: el Visor no consume nada del hosting, y Gestión usa miniaturas de ~20 KB en vez de
las fotos completas.

Consumo esperado del hosting: unos pocos MB por día (consultas de 300 bytes cada minuto) más
lo que subas vos. Muy por debajo de 5 GB/mes.

---

## 1. En el hosting (una vez)

1. Subir el código (git pull / cPanel) como siempre.
2. Editar `backend/config.php` **del hosting** y agregar, junto a `GESTION_PASSWORD`:
   ```php
   define('DEVICE_TOKEN', 'PEGAR_UN_TEXTO_LARGO_Y_AL_AZAR');   // 40+ caracteres
   ```
   Para generar uno: `php -r "echo bin2hex(random_bytes(24));"`
   - **No** agregar `ALLOW_OPEN` en el hosting.
   - Recomendado: cambiar `GESTION_PASSWORD` por una contraseña nueva (o por un hash de
     `password_hash('la-clave', PASSWORD_DEFAULT)`; el login acepta ambos).
   - Recomendado: en `config.php`, en el bloque de error de conexión, borrar la línea
     `'details' => $e->getMessage()` para no mostrar datos internos.
3. Generar las miniaturas de lo ya subido (una vez), logueado en Gestión:
   abrir `https://TU-DOMINIO/PortaRetrato/backend/backfill_thumbs.php`.
   Los videos viejos generan su portada solos la primera vez que los abrís en Gestión.

## 2. En la PC del portarretrato

1. Instalar **Laragon** (Apache + PHP; no hace falta MySQL). Copiar o clonar el proyecto en
   `C:\laragon\www\PortaRetrato`.
2. Copiar `backend\mirror.config.sample.php` como `backend\mirror.config.php` y completar:
   - `remote`: URL del proyecto en el hosting, sin barra final.
   - `token`: el mismo `DEVICE_TOKEN` del hosting.
3. Probar el agente a mano (desde una consola en la carpeta del proyecto):
   ```
   php agente\sync.php
   ```
   Debe terminar sin errores y llenar `uploads\` y `mirror\manifest.json`. El detalle queda en
   `mirror\sync.log`. Si `curl` da error de certificado, descomentar `cainfo` en
   `mirror.config.php` y apuntarlo a un `cacert.pem`.
4. Programarlo cada minuto (consola **como administrador**; ajustar la ruta de `php.exe`):
   ```
   schtasks /Create /TN "PortaRetrato Sync" /SC MINUTE /MO 1 /RU SYSTEM /F ^
     /TR "C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe C:\laragon\www\PortaRetrato\agente\sync.php"
   ```
5. Poner un acceso directo a `iniciar-visor.bat` en `shell:startup` (abre Chrome en modo
   kiosco contra `http://localhost/PortaRetrato/`). Configurar el autologin de Windows y
   desactivar la suspensión.

## 3. Cómo se usa

- Subís/ordenás/borrás desde Gestión (en el hosting) como siempre.
- En ≤ 1 minuto la PC baja lo nuevo y borra lo que sacaste. Sin internet el Visor sigue
  mostrando lo último que bajó.
- Solo se sincroniza el **álbum activo** y el Pase Rápido.

## 4. Verificar

- `mirror\sync.log` muestra "descargados N" cuando hay novedades y nada si no cambió nada.
- Desconectar internet: el Visor sigue andando; el reloj y el clima se actualizan al volver.
- En Gestión → Diagnóstico aparece "Espejo PC (agente)" con lo bajado en el mes.

## Qué tener en cuenta

- El agente **borra de `uploads\` de la PC** todo lo que no esté en el álbum activo: la PC es
  un espejo, no un lugar para guardar fotos propias.
- La vista previa (ojo) de Gestión descarga el álbum completo desde el hosting; usarla poco.
- Si el hosting muestra su pantalla "One moment, please" (filtro Imunify) a los pedidos del
  agente, `sync.log` lo dirá ("manifest inválido" / "falló …text/html"). Pedir a WNPower que
  agregue la IP de la PC a la lista blanca.
