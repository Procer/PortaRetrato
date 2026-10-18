<?php
// Arranque común de api.php y upload.php. Compatible con PHP 7.4.
//
// - Modo ESPEJO: si existe backend/mirror.config.php, esta instalación es la PC del
//   portarretrato: lee todo de su copia local y NO necesita base de datos.
// - Autenticación: las acciones de Gestión exigen sesión; las del dispositivo
//   (manifest, reporte de estado) aceptan además el token DEVICE_TOKEN.
//
// Constantes opcionales (se definen en backend/config.php de cada entorno):
//   DEVICE_TOKEN  string largo y secreto compartido con el agente / Visor.
//   ALLOW_OPEN    true SOLO en la PC de desarrollo: desactiva la autenticación.

define('MIRROR_CONFIG_FILE', __DIR__ . '/mirror.config.php');
define('MIRROR_DIR', dirname(__DIR__) . '/mirror');

function mirrorConfig(): ?array {
    static $cfg = false;
    if ($cfg === false) {
        $cfg = is_file(MIRROR_CONFIG_FILE) ? (include MIRROR_CONFIG_FILE) : null;
        if (!is_array($cfg)) $cfg = null;
    }
    return $cfg;
}

function jsonFail(int $code, string $msg): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $msg]);
    exit;
}

function hasAdminSession(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start(['read_and_close' => true]);
    }
    return !empty($_SESSION['gestion_auth']);
}

function hasDeviceToken(): bool {
    if (!defined('DEVICE_TOKEN') || DEVICE_TOKEN === '') return false;
    $sent = $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '';
    return $sent !== '' && hash_equals(DEVICE_TOKEN, $sent);
}

/** $allowDevice = true para acciones que también puede pedir el Visor/agente. */
function requireAuth(bool $allowDevice = false): void {
    if (defined('ALLOW_OPEN') && ALLOW_OPEN === true) return;
    if (hasAdminSession()) return;
    if ($allowDevice && hasDeviceToken()) return;
    jsonFail(401, 'No autorizado');
}

// ─── Miniaturas ──────────────────────────────────────────────────────────────
// uploads/foo.webp  →  uploads/thumbs/foo.webp   (siempre 400 px de lado mayor)

function thumbRelPath(string $ruta): string {
    $ruta = preg_replace('/\?.*$/', '', $ruta);
    return 'uploads/thumbs/' . pathinfo($ruta, PATHINFO_FILENAME) . (function_exists('imagewebp') ? '.webp' : '.jpg');
}

/** Ruta relativa de la miniatura (con ?v=) si existe en disco, o null. */
function thumbIfExists(string $ruta): ?string {
    $rel = thumbRelPath($ruta);
    $mt = @filemtime(dirname(__DIR__) . '/' . $rel);
    return $mt ? $rel . '?v=' . $mt : null;
}

function deleteThumb(string $ruta): void {
    $p = dirname(__DIR__) . '/' . thumbRelPath($ruta);
    if (is_file($p)) @unlink($p);
}

/** Genera una miniatura de $maxDim px desde cualquier imagen. true si salió bien. */
function makeThumb(string $source, string $destFull, int $maxDim = 400): bool {
    $info = @getimagesize($source);
    if (!$info) return false;
    $creators = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG  => 'imagecreatefrompng',
        IMAGETYPE_GIF  => 'imagecreatefromgif',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
    ];
    $creator = $creators[$info[2]] ?? null;
    if (!$creator || !function_exists($creator)) return false;
    $src = @$creator($source);
    if (!$src) return false;

    $w = $info[0]; $h = $info[1];
    $scale = min(1, $maxDim / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    $dir = dirname($destFull);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $ok = substr($destFull, -5) === '.webp' ? imagewebp($dst, $destFull, 72) : imagejpeg($dst, $destFull, 78);
    imagedestroy($dst);
    return (bool)$ok;
}
