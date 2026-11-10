<?php
// Arranque común de api.php y upload.php. Compatible con PHP 7.4.
//
// Autenticación: las acciones de Gestión exigen sesión; las que lee el Visor
// (datos para reproducir, mensajes, clima) son públicas dentro de la red local.
//
// Constante opcional (backend/config.local.php):
//   ALLOW_OPEN  true SOLO en la PC de desarrollo: desactiva la autenticación.

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

function requireAuth(): void {
    if (defined('ALLOW_OPEN') && ALLOW_OPEN === true) return;
    if (hasAdminSession()) return;
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

// ─── Ajustes, clave de Gestión y acciones de sistema ─────────────────────────
function getSetting(PDO $pdo, string $key, string $default = ''): string {
    $st = $pdo->prepare("SELECT valor FROM settings WHERE clave = ?");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false || $v === null ? $default : (string)$v;
}

function setSetting(PDO $pdo, string $key, string $value): void {
    $pdo->prepare("REPLACE INTO settings (clave, valor) VALUES (?, ?)")->execute([$key, $value]);
}

/** Funciones que el panel de administración puede activar/desactivar (backend/features.json). */
function featOn(PDO $pdo, string $key): bool { return getSetting($pdo, 'feat_' . $key, '1') !== '0'; }

/** La clave cambiada desde Gestión (hash en la base) manda sobre la de config.local.php. */
function checkGestionPassword(PDO $pdo, string $input): bool {
    $hash = getSetting($pdo, 'gestion_pass_hash');
    if ($hash !== '') return password_verify($input, $hash);
    $p = GESTION_PASSWORD;
    $isHash = strpos($p, '$2y$') === 0 || strpos($p, '$argon') === 0;
    return $isHash ? password_verify($input, $p) : hash_equals($p, $input);
}

/** Estado de la red según el servicio portaretrato-red (/run/portaretrato). */
function redEstado(): array {
    $d = '/run/portaretrato';
    $modo = is_file("$d/modo") ? trim((string)@file_get_contents("$d/modo")) : '';
    $setup = ($modo === 'ap' && is_file("$d/setup.json")) ? json_decode((string)@file_get_contents("$d/setup.json"), true) : null;
    $err = is_file("$d/error") ? trim((string)@file_get_contents("$d/error")) : '';
    return ['modo' => $modo, 'setup' => $setup, 'error' => $err];
}

/** Ejecuta una acción de /usr/local/bin/pr-admin (sudo, sin shell). Devuelve el JSON que responde. */
function prAdmin(array $args, string $stdin = ''): array {
    $bin = '/usr/local/bin/pr-admin';
    if (!is_file($bin)) return ['error' => 'Esta función solo está disponible en el portarretrato'];
    $p = @proc_open(array_merge(['sudo', '-n', $bin], $args), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($p)) return ['error' => 'No se pudo ejecutar'];
    fwrite($pipes[0], $stdin); fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]);
    proc_close($p);
    $j = json_decode($out, true);
    return is_array($j) ? $j : (json_decode(json_encode($j), true) ?: ['error' => 'Respuesta inválida']);
}
