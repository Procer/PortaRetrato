<?php
require_once 'bootstrap.php';
require_once 'config.php';
require_once 'sync_lib.php';
requireAuth();

/**
 * Comprime y redimensiona una imagen a máx 1920px.
 * Devuelve la extensión final usada ('webp' o 'jpg') o false si falla.
 */
function compressImage(string $source, string $destNoExt) {
    $info = @getimagesize($source);
    if (!$info) return false;

    [$origW, $origH, $imgType] = $info;

    $creators = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG  => 'imagecreatefrompng',
        IMAGETYPE_GIF  => 'imagecreatefromgif',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
    ];
    $creator = $creators[$imgType] ?? null;
    if (!$creator) return false;
    $src = $creator($source);
    if (!$src) return false;

    $maxDim = 1920;
    if ($origW > $maxDim || $origH > $maxDim) {
        if ($origW >= $origH) {
            $newW = $maxDim;
            $newH = (int)round($origH * $maxDim / $origW);
        } else {
            $newH = $maxDim;
            $newW = (int)round($origW * $maxDim / $origH);
        }
    } else {
        [$newW, $newH] = [$origW, $origH];
    }

    $canvas = imagecreatetruecolor($newW, $newH);

    // Intentar WebP primero (25-35% más pequeño que JPEG a misma calidad)
    if (function_exists('imagewebp')) {
        // Para WebP preservamos transparencia en lugar de fondo blanco
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopyresampled($canvas, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        $dest = $destNoExt . '.webp';
        $result = imagewebp($canvas, $dest, 82);
        imagedestroy($src);
        imagedestroy($canvas);
        return $result ? 'webp' : false;
    }

    // Fallback: JPEG 85%
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagecopyresampled($canvas, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    $dest = $destNoExt . '.jpg';
    $result = imagejpeg($canvas, $dest, 85);
    imagedestroy($src);
    imagedestroy($canvas);
    return $result ? 'jpg' : false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ─── Imagen de reposo (pantalla fija fuera del horario del Visor) ─────────
    // Sube una sola imagen, la comprime igual que el resto y guarda su ruta en
    // settings.visor_off_image. Borra la imagen de reposo anterior.
    if (($_POST['rest_image'] ?? '0') === '1') {
        if (!isset($_FILES['media']['tmp_name'][0]) || $_FILES['media']['error'][0] !== UPLOAD_ERR_OK) {
            die(json_encode(['error' => 'No se recibió la imagen']));
        }
        $dir = '../uploads/';
        if (!file_exists($dir)) mkdir($dir, 0777, true);

        $base = 'rest_' . uniqid();
        $tmp  = $dir . $base . '.tmp';
        move_uploaded_file($_FILES['media']['tmp_name'][0], $tmp);
        $finalExt = compressImage($tmp, $dir . $base);
        @unlink($tmp);
        if ($finalExt === false) die(json_encode(['error' => 'Formato de imagen no soportado (probá JPG o PNG)']));

        $ruta = 'uploads/' . $base . '.' . $finalExt;

        $prev = $pdo->query("SELECT valor FROM settings WHERE clave = 'visor_off_image'")->fetchColumn();
        if ($prev) {
            $prevPath = '../' . preg_replace('/\?.*$/', '', $prev);
            if (file_exists($prevPath) && $prevPath !== '../' . $ruta) @unlink($prevPath);
        }
        $pdo->prepare("REPLACE INTO settings (clave, valor) VALUES ('visor_off_image', ?)")->execute([$ruta]);
        die(json_encode(['success' => true, 'ruta' => $ruta]));
    }

    // ─── Pase Rápido: imágenes sueltas con día y horario ──────────────────────
    if (($_POST['quick_show'] ?? '0') === '1') {
        $dir = '../uploads/quick_show/';
        if (!file_exists($dir)) mkdir($dir, 0777, true);
        $uploaded = 0;
        $failed = [];
        foreach ($_FILES['media']['name'] as $key => $name) {
            if ($_FILES['media']['error'][$key] !== UPLOAD_ERR_OK) { $failed[] = $name; continue; }
            $base = uniqid();
            $temp = $dir . $base . '.tmp';
            move_uploaded_file($_FILES['media']['tmp_name'][$key], $temp);
            $finalExt = compressImage($temp, $dir . $base);
            @unlink($temp);
            if ($finalExt === false) { $failed[] = $name; continue; }
            $ruta = 'uploads/quick_show/' . $base . '.' . $finalExt;
            makeThumb('../' . $ruta, '../' . thumbRelPath($ruta));
            $pdo->prepare("INSERT INTO quick_show_media (ruta, tipo) VALUES (?, 'imagen')")->execute([$ruta]);
            $uploaded++;
        }
        echo json_encode(['success' => true, 'uploaded' => $uploaded, 'failed' => $failed]);
        exit;
    }

    // ─── Álbum: el original va a la carpeta sincronizada y el importador hace el resto ─
    $album_id = (int)($_POST['album_id'] ?? 0);
    if (!$album_id) die(json_encode(['error' => 'ID de álbum no proporcionado']));
    if (!syncFolderReady()) die(json_encode(['error' => 'La carpeta de sincronización no está lista']));

    $stmt = $pdo->prepare("SELECT nombre, carpeta FROM albums WHERE id = ?");
    $stmt->execute([$album_id]);
    $album = $stmt->fetch();
    if (!$album) die(json_encode(['error' => 'Álbum no encontrado']));
    $carpeta = $album['carpeta'];
    if ($carpeta === null) {   // álbum anterior a las carpetas: se le asigna una
        $carpeta = safeFolderName($album['nombre']);
        $pdo->prepare("UPDATE albums SET carpeta = ? WHERE id = ?")->execute([$carpeta, $album_id]);
    }
    $destDir = syncDir() . ($carpeta === '' ? '' : '/' . $carpeta);
    if (!is_dir($destDir)) @mkdir($destDir, 0755, true);

    $uploaded = 0;
    $failed   = [];
    foreach ($_FILES['media']['name'] as $key => $name) {
        if ($_FILES['media']['error'][$key] !== UPLOAD_ERR_OK || !syncMediaType($name)) { $failed[] = $name; continue; }
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $stem = safeFolderName(pathinfo($name, PATHINFO_FILENAME));
        $dest = "$destDir/$stem.$ext";
        for ($n = 2; file_exists($dest); $n++) $dest = "$destDir/$stem $n.$ext";
        if (!move_uploaded_file($_FILES['media']['tmp_name'][$key], $dest)) { $failed[] = $name; continue; }
        $uploaded++;
    }
    if ($uploaded) spawnImport();
    echo json_encode(['success' => true, 'uploaded' => $uploaded, 'failed' => $failed]);
}
