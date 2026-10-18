<?php
// Agente de sincronización — corre en la PC del portarretrato (PHP CLI, cada 1 min
// por Tarea Programada). Baja del hosting cada foto/video UNA sola vez y deja todo
// en uploads/ + mirror/manifest.json, de donde lo lee el Visor local.
//
// Costo normal por corrida sin cambios: 1 GET condicional (ETag → 304, ~300 bytes).
// Compatible con PHP 7.4+.

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$root = dirname(__DIR__);
$cfgFile = $root . '/backend/mirror.config.php';
if (!is_file($cfgFile)) { fwrite(STDERR, "Falta backend/mirror.config.php (copiá mirror.config.sample.php)\n"); exit(2); }
$cfg = include $cfgFile;
$remote = rtrim($cfg['remote'], '/');
$token  = $cfg['token'];

$mirrorDir = $root . '/mirror';
if (!is_dir($mirrorDir)) mkdir($mirrorDir, 0755, true);

function logLine(string $msg): void {
    global $mirrorDir;
    $f = $mirrorDir . '/sync.log';
    if (is_file($f) && filesize($f) > 200000) @rename($f, $f . '.old');
    @file_put_contents($f, date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

// Una sola instancia a la vez.
$lock = fopen($mirrorDir . '/sync.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit(0);

$stateFile = $mirrorDir . '/state.json';
$state = is_file($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];
if (!is_array($state)) $state = [];
$state += ['etag' => '', 'files' => [], 'months' => []];

function curlBase(string $url, array $cfg): array {
    $o = [
        CURLOPT_URL => $url,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => '',                       // acepta gzip
        CURLOPT_USERAGENT => 'PortaRetratoAgente/1.0',
    ];
    if (!empty($cfg['cainfo'])) $o[CURLOPT_CAINFO] = $cfg['cainfo'];
    return $o;
}

// ─── 1) Manifest condicional ─────────────────────────────────────────────────
$headers = ['X-Device-Token: ' . $token];
if ($state['etag'] !== '' && is_file($mirrorDir . '/manifest.json')) $headers[] = 'If-None-Match: ' . $state['etag'];
$newEtag = '';
$ch = curl_init();
curl_setopt_array($ch, curlBase($remote . '/backend/api.php?action=get_sync_manifest', $cfg) + [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$newEtag) {
        if (stripos($h, 'etag:') === 0) $newEtag = trim(substr($h, 5));
        return strlen($h);
    },
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$err  = curl_error($ch);

if ($code === 304) exit(0);
if ($body === false || $code !== 200) { logLine("manifest: sin conexión o error ($code $err)"); exit(1); }
$manifest = json_decode($body, true);
if (!is_array($manifest) || !isset($manifest['version_hash'], $manifest['media'])) {
    logLine('manifest inválido (¿filtro del hosting o token incorrecto?): ' . substr($body, 0, 80));
    exit(1);
}

// ─── 2) Lista de archivos que deben existir localmente ───────────────────────
$wanted = [];   // rel => ['ver' => string, 'bytes' => int]
$add = function ($ruta, $bytes) use (&$wanted) {
    if (!is_string($ruta) || $ruta === '') return;
    $rel = preg_replace('/\?.*$/', '', $ruta);
    if (!preg_match('#^uploads/[A-Za-z0-9_./-]+$#', $rel) || strpos($rel, '..') !== false) return;
    preg_match('/[?&]v=(\d+)/', $ruta, $m);
    $wanted[$rel] = ['ver' => ($m[1] ?? '0') . ':' . (int)$bytes, 'bytes' => (int)$bytes];
};
foreach ($manifest['media'] as $m)            $add($m['ruta'] ?? '', $m['bytes'] ?? 0);
foreach ($manifest['quick_show'] ?? [] as $q) $add($q['ruta'] ?? '', $q['bytes'] ?? 0);
if (!empty($manifest['settings']['visor_off_image'])) $add($manifest['settings']['visor_off_image'], 0);

// ─── 3) Descargas ────────────────────────────────────────────────────────────
$downloaded = 0; $downBytes = 0; $failed = 0;
foreach ($wanted as $rel => $info) {
    $local = $root . '/' . $rel;
    if (is_file($local) && ($state['files'][$rel] ?? '') === $info['ver']) continue;

    $free = @disk_free_space($root);
    if ($free !== false && $free < 500 * 1024 * 1024) { logLine('POCO DISCO LIBRE: se frena la descarga'); $failed++; break; }

    $dir = dirname($local);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $tmp = $local . '.part';
    $fh = fopen($tmp, 'wb');
    $ch = curl_init();
    curl_setopt_array($ch, curlBase($remote . '/' . $rel, $cfg) + [
        CURLOPT_FILE => $fh, CURLOPT_TIMEOUT => 1800,
        CURLOPT_HTTPHEADER => ['X-Device-Token: ' . $token],
    ]);
    $ok = curl_exec($ch);
    $hc = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    fclose($fh);

    $size = @filesize($tmp);
    $good = $ok && $hc === 200 && stripos($ct, 'text/html') === false
         && ($info['bytes'] <= 0 || $size === $info['bytes']);
    if ($good) {
        @rename($tmp, $local);
        $state['files'][$rel] = $info['ver'];
        $downloaded++; $downBytes += $size;
    } else {
        @unlink($tmp);
        $failed++;
        logLine("falló $rel (http $hc, $ct, $size bytes, esperado {$info['bytes']})");
    }
}

// ─── 4) Borrar lo que ya no se usa ───────────────────────────────────────────
if ($failed === 0 && is_dir($root . '/uploads')) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/uploads', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $rel = 'uploads/' . str_replace('\\', '/', substr($f->getPathname(), strlen($root . '/uploads/')));
        if (!isset($wanted[$rel])) @unlink($f->getPathname());
    }
    foreach ($state['files'] as $rel => $_) if (!isset($wanted[$rel])) unset($state['files'][$rel]);
}

// ─── 5) Manifest local: solo lo que ya está en disco ─────────────────────────
$have = function ($ruta) use ($root) { return is_file($root . '/' . preg_replace('/\?.*$/', '', (string)$ruta)); };
$manifest['media']      = array_values(array_filter($manifest['media'], function ($m) use ($have) { return $have($m['ruta'] ?? ''); }));
$manifest['quick_show'] = array_values(array_filter($manifest['quick_show'] ?? [], function ($q) use ($have) { return $have($q['ruta'] ?? ''); }));
if (!empty($manifest['settings']['visor_off_image']) && !$have($manifest['settings']['visor_off_image'])) $manifest['settings']['visor_off_image'] = '';
// Con descargas pendientes el hash cambia igual, para que el Visor recargue cuando llegue el resto.
if ($failed > 0) $manifest['version_hash'] = md5($manifest['version_hash'] . count($manifest['media']) . count($manifest['quick_show']));

if (empty($manifest['settings'])) $manifest['settings'] = new stdClass;   // objeto JSON, no []
$tmpM = $mirrorDir . '/manifest.json.tmp';
file_put_contents($tmpM, json_encode($manifest));
rename($tmpM, $mirrorDir . '/manifest.json');

// Sin etag guardado → la próxima corrida vuelve a pedir el manifest completo y reintenta.
$state['etag'] = ($failed === 0) ? $newEtag : '';
$month = date('Y-m');
$state['months'][$month] = ($state['months'][$month] ?? 0) + $downBytes;
$state['months'] = array_slice($state['months'], -6, null, true);
file_put_contents($stateFile, json_encode($state));

if ($downloaded > 0 || $failed > 0) {
    logLine(sprintf('descargados %d (%.1f MB), fallidos %d, total activo %d', $downloaded, $downBytes / 1048576, $failed, count($wanted)));
}

// Aviso liviano al Diagnóstico cuando hubo movimiento.
if ($downloaded > 0) {
    $ch = curl_init();
    curl_setopt_array($ch, curlBase($remote . '/backend/api.php?action=report_device_stats', $cfg) + [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Device-Token: ' . $token],
        CURLOPT_POSTFIELDS => json_encode([
            'device_id' => 'espejo-pc', 'nombre' => 'Espejo PC (agente)', 'online' => true,
            'media_total' => count($manifest['media']), 'version_hash' => $manifest['version_hash'],
            'descarga_bytes' => $state['months'][$month], 'descarga_archivos' => $downloaded,
            'descarga_desde' => time(), 'ua' => 'agente/sync.php',
        ]),
    ]);
    curl_exec($ch);
}
exit($failed > 0 ? 1 : 0);
