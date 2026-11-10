<?php
// Álbumes = carpetas sincronizadas (Syncthing). Compatible con PHP 7.4.
//
//   <SYNC_DIR>/<Álbum>/<archivo>   originales, tal como llegan del celular
//   uploads/display/<hash>.<ext>   copia que muestra el Visor (imagen: 1920 px WebP; video: enlace)
//   <SYNC_DIR>-papelera/           lo que se borra desde Gestión (se purga a los 30 días)
//
// media.origen = "Álbum/archivo" (la clave); media.ruta = la copia de visualización.
// Los archivos sueltos en la raíz de SYNC_DIR forman el álbum "General" (carpeta '').
// SYNC_DIR se define en backend/config.local.php; si falta se usa <proyecto>/sync.

require_once __DIR__ . '/bootstrap.php';

const SYNC_IMG_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const SYNC_VID_EXT = ['mp4', 'webm', 'ogg', 'mov', 'm4v', 'mkv'];
const GENERAL_ALBUM = 'General';
const SYNC_TRASH_DAYS = 30;

function projectRoot(): string {
    return str_replace('\\', '/', dirname(__DIR__));
}

function syncDir(): string {
    $d = defined('SYNC_DIR') ? SYNC_DIR : projectRoot() . '/sync';
    return rtrim(str_replace('\\', '/', $d), '/');
}

function trashDir(): string {
    return syncDir() . '-papelera';
}

/** Nombre de carpeta válido en Windows/Android a partir de un nombre libre. */
function safeFolderName(string $s): string {
    $s = preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/u', ' ', $s);
    $s = trim(preg_replace('/\s+/u', ' ', $s), " .");
    return $s === '' ? 'Album' : mb_substr($s, 0, 100);
}

/** Syncthing crea .stfolder; sin él la carpeta puede estar desmontada y no hay que borrar nada. */
function syncFolderReady(): bool {
    return is_dir(syncDir() . '/.stfolder');
}

function syncMediaType(string $name): ?string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($ext, SYNC_IMG_EXT, true)) return 'imagen';
    if (in_array($ext, SYNC_VID_EXT, true)) return 'video';
    return null;
}

/** Archivos ignorados: ocultos, temporales y conflictos de Syncthing. */
function syncSkipName(string $name): bool {
    return $name === '' || $name[0] === '.' || substr($name, -4) === '.tmp'
        || strpos($name, '.sync-conflict-') !== false || strpos($name, '~syncthing~') !== false;
}

/**
 * ['' => [archivos sueltos], 'Álbum' => [ ['origen'=>'Álbum/x.jpg','abs'=>…,'mtime'=>…,'size'=>…,'tipo'=>…], … ], …]
 * Cada subcarpeta de primer nivel es un álbum (aunque esté vacía); adentro se recorre todo.
 */
function scanSyncDir(): array {
    $root = syncDir();
    $out = ['' => []];
    foreach (scandir($root) ?: [] as $f) {
        $abs = "$root/$f";
        if ($f[0] === '.' || !is_file($abs) || syncSkipName($f) || !($tipo = syncMediaType($f))) continue;
        $out[''][] = ['origen' => $f, 'abs' => $abs, 'tipo' => $tipo, 'mtime' => (int)filemtime($abs), 'size' => (int)filesize($abs)];
    }
    foreach (scandir($root) ?: [] as $album) {
        if ($album === '.' || $album === '..' || $album[0] === '.' || !is_dir("$root/$album")) continue;
        $out[$album] = [];
        $stack = [''];
        while ($stack) {
            $sub = array_pop($stack);
            $dir = $root . '/' . $album . ($sub === '' ? '' : '/' . $sub);
            foreach (scandir($dir) ?: [] as $f) {
                if ($f === '.' || $f === '..' || $f[0] === '.') continue;
                $abs = "$dir/$f";
                $rel = ($sub === '' ? '' : $sub . '/') . $f;
                if (is_dir($abs)) { $stack[] = $rel; continue; }
                if (syncSkipName($f) || !($tipo = syncMediaType($f))) continue;
                $out[$album][] = [
                    'origen' => "$album/$rel", 'abs' => $abs, 'tipo' => $tipo,
                    'mtime' => (int)filemtime($abs), 'size' => (int)filesize($abs),
                ];
            }
        }
    }
    return $out;
}

function displayRel(string $origen, string $ext): string {
    return 'uploads/display/' . substr(sha1($origen), 0, 20) . '.' . $ext;
}

/** Copia de visualización de una imagen: máx 1920 px, orientación EXIF aplicada. 'webp'|'jpg'|false. */
function makeDisplayImage(string $source, string $destNoExt) {
    $info = @getimagesize($source);
    if (!$info) return false;
    $creators = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng',
        IMAGETYPE_GIF => 'imagecreatefromgif',   IMAGETYPE_WEBP => 'imagecreatefromwebp',
    ];
    $creator = $creators[$info[2]] ?? null;
    if (!$creator || !function_exists($creator)) return false;
    $src = @$creator($source);
    if (!$src) return false;

    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int)(@exif_read_data($source)['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($angle && ($rot = imagerotate($src, $angle, 0))) { imagedestroy($src); $src = $rot; }
    }

    $w = imagesx($src); $h = imagesy($src);
    $scale = min(1, 1920 / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);

    $dir = dirname($destNoExt);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (function_exists('imagewebp')) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = imagewebp($dst, $destNoExt . '.webp', 82); $ext = 'webp';
    } else {
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = imagejpeg($dst, $destNoExt . '.jpg', 85); $ext = 'jpg';
    }
    imagedestroy($src); imagedestroy($dst);
    return $ok ? $ext : false;
}

/** Propiedades del video según ffprobe, o null si no se puede leer. */
function probeVideo(string $path): ?array {
    $cmd = 'ffprobe -v error -select_streams v:0 -show_entries stream=codec_name,width,height -of json ' . escapeshellarg($path) . ' 2>/dev/null';
    $out = @shell_exec($cmd);
    $j = $out ? json_decode($out, true) : null;
    return $j['streams'][0] ?? null;
}

/**
 * Copia de visualización de un video. Si ya es H.264 de hasta 1080 p se enlaza (o copia);
 * si no (HEVC del celular, 4K…) se recodifica a MP4 H.264 1080 p, que reproduce fluido en el kiosk.
 */
function placeVideo(string $source, string $destFull): bool {
    $dir = dirname($destFull);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (is_file($destFull)) @unlink($destFull);

    $info = probeVideo($source);
    $ext = strtolower(pathinfo($destFull, PATHINFO_EXTENSION));
    $simple = $info && ($info['codec_name'] ?? '') === 'h264' && (int)($info['height'] ?? 0) <= 1080
        && (int)($info['width'] ?? 0) <= 1920 && in_array($ext, ['mp4', 'm4v'], true);
    if ($simple || !$info) return @link($source, $destFull) || @copy($source, $destFull);

    $tmp = $destFull . '.part.mp4';
    $cmd = 'nice -n 10 ffmpeg -y -v error -i ' . escapeshellarg($source)
        . ' -vf ' . escapeshellarg("scale='min(1920,iw)':'min(1080,ih)':force_original_aspect_ratio=decrease:force_divisible_by=2")
        . ' -c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 128k -movflags +faststart '
        . escapeshellarg($tmp) . ' 2>&1';
    @shell_exec($cmd);
    if (is_file($tmp) && filesize($tmp) > 0 && @rename($tmp, $destFull)) return true;
    @unlink($tmp);
    return false;
}

/** Portada de un video (fotograma a ~1 s) como miniatura. */
function makeVideoThumb(string $videoFull, string $destFull): bool {
    $dir = dirname($destFull);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $png = $destFull . '.frame.jpg';
    @shell_exec('ffmpeg -y -v error -ss 1 -i ' . escapeshellarg($videoFull) . ' -frames:v 1 ' . escapeshellarg($png) . ' 2>&1');
    if (!is_file($png)) @shell_exec('ffmpeg -y -v error -i ' . escapeshellarg($videoFull) . ' -frames:v 1 ' . escapeshellarg($png) . ' 2>&1');
    $ok = is_file($png) && makeThumb($png, $destFull);
    @unlink($png);
    return $ok;
}

/** Borra la copia de visualización y la miniatura de una fila de media. */
function discardDisplay(string $ruta): void {
    $full = projectRoot() . '/' . preg_replace('/\?.*$/', '', $ruta);
    if (is_file($full)) @unlink($full);
    deleteThumb($ruta);
}

/** Mueve un original a la papelera (fuera de la carpeta sincronizada). */
function trashOriginal(string $origen): void {
    $src = syncDir() . '/' . $origen;
    if (!is_file($src)) return;
    $dest = trashDir() . '/' . date('Ymd-His') . '/' . $origen;
    if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0755, true);
    if (!@rename($src, $dest)) { @copy($src, $dest); @unlink($src); }
}

function purgeTrash(): void {
    $limit = time() - SYNC_TRASH_DAYS * 86400;
    if (!is_dir(trashDir())) return;
    foreach (scandir(trashDir()) ?: [] as $d) {
        if ($d[0] === '.' || filemtime(trashDir() . "/$d") > $limit) continue;
        removeTree(trashDir() . "/$d");
    }
}

function removeTree(string $dir): void {
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        is_dir("$dir/$f") ? removeTree("$dir/$f") : @unlink("$dir/$f");
    }
    @rmdir($dir);
}

/** Quita una fila de media: copia, miniatura, fila y (si $trash) el original a la papelera. */
function removeMediaRow(PDO $pdo, array $row, bool $trash): void {
    discardDisplay($row['ruta']);
    if ($trash && !empty($row['origen'])) trashOriginal($row['origen']);
    $pdo->prepare("DELETE FROM media WHERE id = ?")->execute([$row['id']]);
}

/** Concilia carpeta y BD. Devuelve contadores. $log recibe líneas de texto. */
function importSyncFolder(PDO $pdo, callable $log): array {
    $st = ['nuevos' => 0, 'actualizados' => 0, 'borrados' => 0, 'fallos' => 0, 'albumes' => 0];
    if (!syncFolderReady()) {
        $log('Falta ' . syncDir() . '/.stfolder: la carpeta no está lista, no se toca nada.');
        return $st;
    }

    $failFile = projectRoot() . '/uploads/.fallos.json';
    $fails = is_file($failFile) ? (json_decode(file_get_contents($failFile), true) ?: []) : [];

    $disk = scanSyncDir();
    $albums = $pdo->query("SELECT id, nombre, carpeta FROM albums")->fetchAll();
    $porCarpeta = []; $sinCarpeta = [];
    foreach ($albums as $a) {
        if ($a['carpeta'] !== null) $porCarpeta[$a['carpeta']] = $a;
        else $sinCarpeta[mb_strtolower($a['nombre'])] = $a;
    }

    foreach ($disk as $carpeta => $files) {
        $carpeta = (string)$carpeta;
        if ($carpeta === '' && !$files && !isset($porCarpeta[''])) continue;   // "General" solo existe si hay archivos sueltos
        $nombreAlbum = $carpeta === '' ? GENERAL_ALBUM : $carpeta;
        if (isset($porCarpeta[$carpeta])) {
            $albumId = (int)$porCarpeta[$carpeta]['id'];
        } elseif (isset($sinCarpeta[mb_strtolower($nombreAlbum)])) {
            $albumId = (int)$sinCarpeta[mb_strtolower($nombreAlbum)]['id'];
            $pdo->prepare("UPDATE albums SET carpeta = ? WHERE id = ?")->execute([$carpeta, $albumId]);
        } else {
            $pdo->prepare("INSERT INTO albums (nombre, carpeta) VALUES (?, ?)")->execute([$nombreAlbum, $carpeta]);
            $albumId = (int)$pdo->lastInsertId();
            $st['albumes']++;
            $log("Álbum nuevo: $nombreAlbum");
        }

        $stmt = $pdo->prepare("SELECT id, ruta, origen, origen_mtime, origen_size FROM media WHERE album_id = ? AND origen IS NOT NULL");
        $stmt->execute([$albumId]);
        $rows = [];
        foreach ($stmt->fetchAll() as $r) $rows[$r['origen']] = $r;

        usort($files, function ($a, $b) { return $a['mtime'] <=> $b['mtime'] ?: strcmp($a['origen'], $b['origen']); });
        $orden = (int)$pdo->query("SELECT COALESCE(MAX(orden), -1) + 1 FROM media WHERE album_id = $albumId")->fetchColumn();

        foreach ($files as $f) {
            $row = $rows[$f['origen']] ?? null;
            unset($rows[$f['origen']]);
            $sig = $f['mtime'] . ':' . $f['size'];
            $displayOk = $row && is_file(projectRoot() . '/' . preg_replace('/\?.*$/', '', $row['ruta']));
            if ($row && $displayOk && (int)$row['origen_mtime'] === $f['mtime'] && (int)$row['origen_size'] === $f['size']) continue;
            if (($fails[$f['origen']] ?? null) === $sig) continue;

            $hash = substr(sha1($f['origen']), 0, 20);
            if ($f['tipo'] === 'imagen') {
                $ext = makeDisplayImage($f['abs'], projectRoot() . '/uploads/display/' . $hash);
                $ok = $ext !== false;
            } else {
                $ext = 'mp4';
                $ok = placeVideo($f['abs'], projectRoot() . "/uploads/display/$hash.mp4");
            }
            if (!$ok) {
                $fails[$f['origen']] = $sig;
                $st['fallos']++;
                $log("No se pudo procesar: {$f['origen']}");
                continue;
            }
            unset($fails[$f['origen']]);
            $ruta = "uploads/display/$hash.$ext";
            deleteThumb($ruta);
            if ($f['tipo'] === 'imagen') makeThumb(projectRoot() . '/' . $ruta, projectRoot() . '/' . thumbRelPath($ruta));
            else makeVideoThumb(projectRoot() . '/' . $ruta, projectRoot() . '/' . thumbRelPath($ruta));

            if ($row) {
                $pdo->prepare("UPDATE media SET ruta = ?, tipo = ?, origen_mtime = ?, origen_size = ? WHERE id = ?")
                    ->execute([$ruta, $f['tipo'], $f['mtime'], $f['size'], $row['id']]);
                $st['actualizados']++;
            } else {
                $pdo->prepare("INSERT INTO media (album_id, ruta, tipo, orden, origen, origen_mtime, origen_size) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$albumId, $ruta, $f['tipo'], $orden++, $f['origen'], $f['mtime'], $f['size']]);
                $st['nuevos']++;
            }
        }

        // Lo que ya no está en la carpeta (borrado en el celular o en la notebook).
        foreach ($rows as $r) {
            removeMediaRow($pdo, $r + ['id' => $r['id']], false);
            $st['borrados']++;
            $log("Quitado: {$r['origen']}");
        }
    }

    // Álbumes cuya carpeta desapareció.
    foreach ($porCarpeta as $carpeta => $a) {
        if (isset($disk[$carpeta]) || $carpeta === '') continue;
        $stmt = $pdo->prepare("SELECT id, ruta, origen FROM media WHERE album_id = ?");
        $stmt->execute([$a['id']]);
        foreach ($stmt->fetchAll() as $r) discardDisplay($r['ruta']);
        $pdo->prepare("DELETE FROM albums WHERE id = ?")->execute([$a['id']]);
        $log("Álbum quitado (carpeta borrada): $carpeta");
    }

    @file_put_contents($failFile, json_encode($fails));
    purgeTrash();
    return $st;
}

// ─── Ejecución del importador ────────────────────────────────────────────────

/** Corre importSyncFolder con candado y deja el resultado en agente/importar.log. null si ya hay otra corrida. */
function runImport(PDO $pdo): ?array {
    $lock = fopen(sys_get_temp_dir() . '/portaretrato-importar.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return null;

    $lines = [];
    $log = function (string $m) use (&$lines) { $lines[] = '[' . date('Y-m-d H:i:s') . '] ' . $m; };
    $st = null;
    try {
        $st = importSyncFolder($pdo, $log);
        if ($st['nuevos'] || $st['actualizados'] || $st['borrados'] || $st['fallos'] || $st['albumes']) {
            $log(sprintf('Listo: %d nuevos, %d actualizados, %d quitados, %d fallos', $st['nuevos'], $st['actualizados'], $st['borrados'], $st['fallos']));
        }
    } catch (Throwable $e) {
        $log('ERROR: ' . $e->getMessage());
    }
    if ($lines) {
        $logFile = projectRoot() . '/agente/importar.log';
        if (is_file($logFile) && filesize($logFile) > 512 * 1024) @rename($logFile, $logFile . '.old');
        @file_put_contents($logFile, implode("\n", $lines) . "\n", FILE_APPEND);
    }
    flock($lock, LOCK_UN);
    return $st ?? ['nuevos' => 0, 'actualizados' => 0, 'borrados' => 0, 'fallos' => 1, 'albumes' => 0];
}

/** Lanza el importador en segundo plano (no espera). Lo usa Gestión tras subir archivos. */
function spawnImport(): void {
    $php = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg(projectRoot() . '/agente/importar.php');
    if (stripos(PHP_OS, 'WIN') === 0) pclose(popen('start /B ' . $cmd . ' > NUL 2>&1', 'r'));
    else exec($cmd . ' > /dev/null 2>&1 &');
}

/** Cuántos archivos de la carpeta todavía no están en la base (se están procesando). */
function pendingImportCount(PDO $pdo): int {
    if (!syncFolderReady()) return 0;
    $known = [];
    foreach ($pdo->query("SELECT origen, origen_mtime, origen_size FROM media WHERE origen IS NOT NULL")->fetchAll() as $r) {
        $known[$r['origen']] = $r['origen_mtime'] . ':' . $r['origen_size'];
    }
    $n = 0;
    foreach (scanSyncDir() as $files) {
        foreach ($files as $f) {
            if (($known[$f['origen']] ?? null) !== $f['mtime'] . ':' . $f['size']) $n++;
        }
    }
    return $n;
}
