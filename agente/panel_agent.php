<?php
// Agente del panel de administración. Lo ejecuta systemd cada ~10 minutos como root
// (portaretrato-agente.timer). Solo hace conexiones SALIENTES (HTTPS) hacia el panel:
// no hay que abrir nada en el router de la casa.
//
//   1. Se registra la primera vez (enroll) con el código que viene en /root/pr.env.
//   2. Avisa que está vivo y manda su estado (versión, disco, WiFi, fotos, último update).
//   3. Recibe del panel: funciones activadas/desactivadas, canal de actualización
//      (stable | main) y comandos (actualizar ya, reiniciar, reabrir el Visor).
//
// Si no hay panel configurado o no hay internet, no hace nada y el equipo sigue andando igual.
// Configuración en /var/lib/portaretrato/panel.json (la crea instalar-servidor.sh).

if (PHP_SAPI !== 'cli') exit;
const STATE_DIR = '/var/lib/portaretrato';
const CFG_FILE  = STATE_DIR . '/panel.json';
const APP       = '/opt/portaretrato';

function logm($m) { fwrite(STDERR, date('H:i:s ') . $m . "\n"); }

$cfg = is_file(CFG_FILE) ? json_decode(file_get_contents(CFG_FILE), true) : null;
if (!$cfg || empty($cfg['url'])) exit(0);      // sin panel: nada que hacer
$base = rtrim($cfg['url'], '/');

function saveCfg($cfg) { file_put_contents(CFG_FILE, json_encode($cfg, JSON_PRETTY_PRINT)); chmod(CFG_FILE, 0600); }

function post($base, $path, $data, $cfg = null) {
    $ch = curl_init($base . $path);
    $h = ['Content-Type: application/json'];
    if ($cfg && !empty($cfg['device_id'])) { $h[] = 'X-Device: ' . $cfg['device_id']; $h[] = 'X-Token: ' . ($cfg['token'] ?? ''); }
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($data), CURLOPT_HTTPHEADER => $h,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 10]);
    $out = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return [$code, $out === false ? null : json_decode($out, true)];
}

// ── Base de datos local ──────────────────────────────────────────────────────
require_once APP . '/backend/config.local.php';
try {
    $pdo = new PDO('mysql:host=' . (defined('DB_HOST') ? DB_HOST : '127.0.0.1') . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (Exception $e) { logm('Sin base de datos: ' . $e->getMessage()); exit(1); }

function setting(PDO $pdo, $k, $d = '') { $s = $pdo->prepare('SELECT valor FROM settings WHERE clave=?'); $s->execute([$k]); $v = $s->fetchColumn(); return $v === false ? $d : $v; }
function setSet(PDO $pdo, $k, $v) { $pdo->prepare('REPLACE INTO settings (clave, valor) VALUES (?, ?)')->execute([$k, $v]); }
function sh($cmd) { return trim((string)shell_exec($cmd . ' 2>/dev/null')); }

// ── Registro (primera vez) ───────────────────────────────────────────────────
if (empty($cfg['token'])) {
    if (empty($cfg['device_id'])) { $cfg['device_id'] = bin2hex(random_bytes(12)); saveCfg($cfg); }
    list($code, $r) = post($base, '/api/enroll.php', [
        'device_id' => $cfg['device_id'], 'enroll' => $cfg['enroll'] ?? '', 'host' => gethostname(),
        'channel' => $cfg['channel'] ?? 'stable',
    ]);
    if ($code === 200 && !empty($r['token'])) { $cfg['token'] = $r['token']; saveCfg($cfg); logm('Registrado en el panel'); }
    else { logm('No se pudo registrar (HTTP ' . $code . ')'); exit(0); }
}

// ── Estado para informar ─────────────────────────────────────────────────────
$last = is_file(STATE_DIR . '/last-update.json') ? json_decode(file_get_contents(STATE_DIR . '/last-update.json'), true) : null;
$net = @json_decode(sh('/usr/local/bin/pr-admin wifi-status'), true) ?: [];
$cnt = $pdo->query("SELECT SUM(tipo='imagen') f, SUM(tipo='video') v FROM media")->fetch();
$results = is_file(STATE_DIR . '/results.json') ? (json_decode(file_get_contents(STATE_DIR . '/results.json'), true) ?: []) : [];
$status = [
    'version'   => sh('runuser -u frame -- git -C ' . APP . ' rev-parse --short HEAD'),
    'branch'    => sh('runuser -u frame -- git -C ' . APP . ' rev-parse --abbrev-ref HEAD'),
    'channel'   => is_file(STATE_DIR . '/channel') ? trim(file_get_contents(STATE_DIR . '/channel')) : ($cfg['channel'] ?? 'stable'),
    'host'      => gethostname(),
    'ips'       => preg_split('/\s+/', sh('hostname -I')),
    'wifi'      => $net['ssid'] ?? '',
    'modo_red'  => $net['modo'] ?? '',
    'disco_pct' => (int)round((1 - disk_free_space(APP) / max(1, disk_total_space(APP))) * 100),
    'uptime_s'  => (int)explode(' ', @file_get_contents('/proc/uptime'))[0],
    'fotos'     => (int)($cnt['f'] ?? 0), 'videos' => (int)($cnt['v'] ?? 0),
    'ciudad'    => setting($pdo, 'weather_city'),
    'setup_ok'  => setting($pdo, 'setup_done', '0') === '1',
    'ultimo_update' => $last,
    'visor_ok'  => sh("curl -s -o /dev/null -w '%{http_code}' http://localhost/") === '200',
    'resultados' => $results,
];
list($code, $r) = post($base, '/api/checkin.php', $status, $cfg);
if ($code === 401 || $code === 403) { logm('El panel rechazó este equipo (¿fue eliminado?)'); exit(0); }
if ($code !== 200 || !is_array($r)) { logm('Panel no disponible (HTTP ' . $code . ')'); exit(0); }
@unlink(STATE_DIR . '/results.json');

// ── Aplicar lo que manda el panel ────────────────────────────────────────────
if (!empty($r['features']) && is_array($r['features'])) {
    foreach ($r['features'] as $k => $on) {
        if (!preg_match('/^[a-z_]{1,30}$/', $k)) continue;
        $new = $on ? '1' : '0';
        if (setting($pdo, 'feat_' . $k, '1') !== $new) { setSet($pdo, 'feat_' . $k, $new); logm("Función $k: " . ($on ? 'activada' : 'desactivada')); }
    }
}
if (!empty($r['channel']) && in_array($r['channel'], ['stable', 'main'], true)) file_put_contents(STATE_DIR . '/channel', $r['channel']);
if (isset($r['name'])) setSet($pdo, 'panel_nombre', (string)$r['name']);

$results = [];
foreach (($r['commands'] ?? []) as $c) {
    $id = (int)($c['id'] ?? 0); $cmd = (string)($c['cmd'] ?? ''); $ok = true; $msg = '';
    switch ($cmd) {
        case 'update':         shell_exec('systemd-run --no-block --unit=portaretrato-update-now /usr/local/bin/pr-actualizar >/dev/null 2>&1'); $msg = 'Actualización iniciada'; break;
        case 'restart_visor':  shell_exec('pkill -x chromium >/dev/null 2>&1'); $msg = 'Visor reabierto'; break;
        case 'reboot':         shell_exec('(sleep 5; systemctl reboot) >/dev/null 2>&1 &'); $msg = 'Reiniciando'; break;
        default: $ok = false; $msg = 'Comando desconocido';
    }
    $results[] = ['id' => $id, 'ok' => $ok, 'msg' => $msg];
}
if ($results) file_put_contents(STATE_DIR . '/results.json', json_encode($results));
