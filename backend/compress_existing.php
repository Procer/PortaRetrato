<?php
/**
 * Script de migración — ejecutar UNA SOLA VEZ para comprimir imágenes existentes.
 * Acceder desde el navegador: /backend/compress_existing.php
 * Borrarlo del servidor una vez terminado.
 */

require_once 'config.php';

set_time_limit(300);
$dirs = [
    '../uploads/'            => 'uploads/',
    '../uploads/quick_show/' => 'uploads/quick_show/',
];
$imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

$totalBefore = 0;
$totalAfter  = 0;
$processed   = 0;
$dbUpdated   = 0;
$errors      = [];

function recompressToJpeg(string $path): bool {
    $info = @getimagesize($path);
    if (!$info) return false;

    [$origW, $origH, $imgType] = $info;

    $src = match ($imgType) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($path),
        IMAGETYPE_PNG  => imagecreatefrompng($path),
        IMAGETYPE_GIF  => imagecreatefromgif($path),
        IMAGETYPE_WEBP => imagecreatefromwebp($path),
        default        => false,
    };
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
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagecopyresampled($canvas, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    $dest = preg_replace('/\.(png|gif|webp|jpeg)$/i', '.jpg', $path);
    $result = imagejpeg($canvas, $dest, 85);

    imagedestroy($src);
    imagedestroy($canvas);

    if ($dest !== $path && $result) {
        @unlink($path);
    }

    return $result;
}

echo "<pre style='font-family:monospace;font-size:13px'>\n";
echo "=== Compresión de imágenes existentes ===\n\n";

foreach ($dirs as $fsDir => $dbPrefix) {
    if (!is_dir($fsDir)) {
        echo "  — Directorio $fsDir no existe, saltando.\n\n";
        continue;
    }
    $files = scandir($fsDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $imageTypes)) continue;

        $fullPath   = $fsDir . $file;
        $sizeBefore = filesize($fullPath);
        $totalBefore += $sizeBefore;

        $ok = recompressToJpeg($fullPath);

        $newFile   = preg_replace('/\.(png|gif|webp|jpeg)$/i', '.jpg', $file);
        $newPath   = $fsDir . $newFile;
        $sizeAfter = file_exists($newPath) ? filesize($newPath) : ($ok ? filesize($fullPath) : $sizeBefore);
        $totalAfter += $sizeAfter;

        if ($ok) {
            $saved = $sizeBefore - $sizeAfter;
            $pct   = $sizeBefore > 0 ? round($saved / $sizeBefore * 100) : 0;
            printf("  ✓ %-50s  %6dKB → %6dKB  (-%d%%)\n",
                $file,
                round($sizeBefore / 1024),
                round($sizeAfter / 1024),
                $pct
            );
            $processed++;

            // Si la extensión cambió, actualizar la BD para que el visor no rompa
            if ($newFile !== $file) {
                $oldRuta = $dbPrefix . $file;
                $newRuta = $dbPrefix . $newFile;

                $stmt = $pdo->prepare("UPDATE media SET ruta = ? WHERE ruta = ?");
                $stmt->execute([$newRuta, $oldRuta]);
                $n = $stmt->rowCount();

                $stmt2 = $pdo->prepare("UPDATE quick_show_media SET ruta = ? WHERE ruta = ?");
                $stmt2->execute([$newRuta, $oldRuta]);
                $n += $stmt2->rowCount();

                if ($n > 0) {
                    echo "      → BD actualizada: $oldRuta → $newRuta ($n fila/s)\n";
                    $dbUpdated += $n;
                }
            }
        } else {
            echo "  ✗ $file  — ERROR\n";
            $errors[] = $file;
        }
    }
}

$savedTotal = $totalBefore - $totalAfter;
echo "\n--- Resumen ---\n";
printf("  Imágenes procesadas : %d\n", $processed);
printf("  Filas BD actualizadas: %d\n", $dbUpdated);
printf("  Errores             : %d\n", count($errors));
printf("  Tamaño antes        : %.1f MB\n", $totalBefore / 1048576);
printf("  Tamaño después      : %.1f MB\n", $totalAfter / 1048576);
printf("  Ahorro total        : %.1f MB\n", $savedTotal / 1048576);
echo "\nListo. Borrá este archivo del servidor.\n";
echo "</pre>\n";
