<?php
require_once 'config.php';

// Tamaño máximo permitido para videos (en bytes). 100 MB.
const VIDEO_MAX_BYTES = 100 * 1024 * 1024;

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
    $is_quick_show = (($_POST['quick_show'] ?? '0') === '1');

    if (!$is_quick_show) {
        $album_id = $_POST['album_id'] ?? 0;
        if (!$album_id) die(json_encode(['error' => 'ID de álbum no proporcionado']));
    }

    $upload_dir = $is_quick_show ? '../uploads/quick_show/' : '../uploads/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

    $uploaded = 0;
    $failed   = [];

    // Las fotos nuevas se agregan al final de la secuencia del álbum en vez de
    // quedar en el "orden" por defecto (0): si empataran con fotos existentes,
    // el desempate por id las pondría antes de todo el contenido ya cargado.
    $next_orden = 0;
    if (!$is_quick_show) {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(orden), -1) + 1 FROM media WHERE album_id = ?");
        $stmt->execute([$album_id]);
        $next_orden = (int)$stmt->fetchColumn();
    }

    foreach ($_FILES['media']['name'] as $key => $name) {
        if ($_FILES['media']['error'][$key] !== UPLOAD_ERR_OK) { $failed[] = $name; continue; }

        $tmp_name = $_FILES['media']['tmp_name'][$key];
        $ext      = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $isVideo  = in_array($ext, ['mp4', 'webm', 'ogg']);

        // Rechazar videos que superen el límite
        if ($isVideo && $_FILES['media']['size'][$key] > VIDEO_MAX_BYTES) {
            $failed[] = $name;
            continue;
        }

        $base_name = uniqid();

        if ($isVideo) {
            $new_name    = $base_name . '.' . $ext;
            $destination = $upload_dir . $new_name;
            $ok = move_uploaded_file($tmp_name, $destination);
        } else {
            $temp        = $upload_dir . $base_name . '.tmp';
            move_uploaded_file($tmp_name, $temp);
            $finalExt    = compressImage($temp, $upload_dir . $base_name);
            @unlink($temp);
            $ok          = ($finalExt !== false);
            $new_name    = $ok ? $base_name . '.' . $finalExt : null;
        }

        if (!$ok || !$new_name) { $failed[] = $name; continue; }

        if ($is_quick_show) {
            $ruta = 'uploads/quick_show/' . $new_name;
            $stmt = $pdo->prepare("INSERT INTO quick_show_media (ruta, tipo) VALUES (?, 'imagen')");
            $stmt->execute([$ruta]);
        } else {
            $type = $isVideo ? 'video' : 'imagen';
            $stmt = $pdo->prepare("INSERT INTO media (album_id, ruta, tipo, orden) VALUES (?, ?, ?, ?)");
            $stmt->execute([$album_id, 'uploads/' . $new_name, $type, $next_orden]);
            $next_orden++;
        }
        $uploaded++;
    }

    echo json_encode(['success' => true, 'uploaded' => $uploaded, 'failed' => $failed]);
}
