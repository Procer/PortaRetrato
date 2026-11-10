<?php
// Una sola vez: pasa los álbumes y archivos ya cargados a la carpeta sincronizada.
//   php agente/migrar_a_carpetas.php
// Copia cada archivo a <SYNC_DIR>/<Álbum>/ (los de uploads/ quedan como copia de visualización)
// y completa albums.carpeta / media.origen. Se puede repetir sin duplicar nada.

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/sync_lib.php';

$root = syncDir();
if (!is_dir("$root/.stfolder")) mkdir("$root/.stfolder", 0755, true);   // Syncthing lo reconoce si ya existe

$usadas = [];
foreach ($pdo->query("SELECT id, nombre, carpeta FROM albums ORDER BY id")->fetchAll() as $a) {
    $carpeta = $a['carpeta'];
    if ($carpeta === null) {
        $base = $carpeta = safeFolderName($a['nombre']);
        for ($n = 2; isset($usadas[mb_strtolower($carpeta)]) || (is_dir("$root/$carpeta") && !is_dir("$root/$carpeta/.")); $n++) $carpeta = "$base $n";
        $pdo->prepare("UPDATE albums SET carpeta = ? WHERE id = ?")->execute([$carpeta, $a['id']]);
    }
    $usadas[mb_strtolower($carpeta)] = true;
    if (!is_dir("$root/$carpeta")) mkdir("$root/$carpeta", 0755, true);

    $stmt = $pdo->prepare("SELECT id, ruta FROM media WHERE album_id = ? AND origen IS NULL ORDER BY orden, id");
    $stmt->execute([$a['id']]);
    $n = 0;
    foreach ($stmt->fetchAll() as $m) {
        $src = projectRoot() . '/' . preg_replace('/\?.*$/', '', $m['ruta']);
        if (!is_file($src)) continue;
        $name = basename($src);
        $dest = "$root/$carpeta/$name";
        if (!is_file($dest)) { copy($src, $dest); touch($dest, filemtime($src)); }
        $pdo->prepare("UPDATE media SET origen = ?, origen_mtime = ?, origen_size = ? WHERE id = ?")
            ->execute(["$carpeta/$name", filemtime($dest), filesize($dest), $m['id']]);
        $n++;
    }
    echo "{$a['nombre']} -> $carpeta ($n archivos)\n";
}
