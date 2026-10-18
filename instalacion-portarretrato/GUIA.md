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

## 2. En la PC del portarretrato (sin Laragon)

1. Extraer `PortaRetrato.zip` en `C:\laragon\www\` (o en cualquier carpeta; ahora puede ser otra).
2. Doble clic en `instalacion-portarretrato\instalar-pc.bat`. Hace todo solo:
   - descarga un PHP portable dentro de la carpeta `php\` (no instala nada en Windows),
   - te pide la URL del hosting y el `DEVICE_TOKEN`,
   - hace una sincronización de prueba (si falla, se detiene y muestra el log),
   - deja el arranque automático en la carpeta Inicio de Windows,
   - desactiva la suspensión y el apagado de pantalla.
3. Inicio de sesión automático de Windows: `Win+R` → `netplwiz` → desmarcar
   "Los usuarios deben escribir su nombre y contraseña".

Al encender la PC, `portaretrato.ps1` (oculto) levanta el servidor local en
`http://127.0.0.1:8080`, sincroniza cada 60 s, abre Chrome (o Edge) en pantalla completa y lo
vuelve a abrir si se cierra. Registro en `mirrorrranque.log` y `mirror\sync.log`.

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
