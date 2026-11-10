<?php
// Núcleo del panel de administración de portarretratos (corre en el VPS).
// PHP 8+, base SQLite en PANEL_DATA (por defecto /var/lib/portaretrato-panel).

const REPO = 'Procer/PortaRetrato';
const ONLINE_MIN = 25;   // minutos sin avisar => "sin conexión"

function dataDir(): string { return getenv('PANEL_DATA') ?: '/var/lib/portaretrato-panel'; }

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    @mkdir(dataDir(), 0750, true);
    $pdo = new PDO('sqlite:' . dataDir() . '/panel.db', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS config (k TEXT PRIMARY KEY, v TEXT);
    CREATE TABLE IF NOT EXISTS devices (
        id TEXT PRIMARY KEY, token_hash TEXT NOT NULL, name TEXT DEFAULT '', owner TEXT DEFAULT '', notes TEXT DEFAULT '',
        channel TEXT DEFAULT 'stable', features TEXT DEFAULT '{}', created_at INTEGER, last_seen INTEGER, status TEXT DEFAULT '{}', host TEXT DEFAULT '');
    CREATE TABLE IF NOT EXISTS commands (
        id INTEGER PRIMARY KEY AUTOINCREMENT, device_id TEXT NOT NULL, cmd TEXT NOT NULL, created_at INTEGER, sent_at INTEGER, ok INTEGER, msg TEXT,
        FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE);
    CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY AUTOINCREMENT, device_id TEXT NOT NULL, ts INTEGER, kind TEXT, msg TEXT,
        FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE);
    CREATE INDEX IF NOT EXISTS ev_dev ON events(device_id, ts DESC);");
    return $pdo;
}

function cfg(string $k, string $d = ''): string {
    $s = db()->prepare('SELECT v FROM config WHERE k=?'); $s->execute([$k]); $v = $s->fetchColumn();
    return $v === false ? $d : (string)$v;
}
function setCfg(string $k, string $v): void { db()->prepare('REPLACE INTO config (k, v) VALUES (?, ?)')->execute([$k, $v]); }

/** Funciones disponibles (las mismas que usa el equipo: backend/features.json del proyecto). */
function featureList(): array {
    foreach ([__DIR__ . '/../backend/features.json', __DIR__ . '/features.json'] as $f)
        if (is_file($f)) return json_decode(file_get_contents($f), true) ?: [];
    return [];
}
function defaultFeatures(): array {
    $saved = json_decode(cfg('default_features', '{}'), true) ?: [];
    $out = [];
    foreach (featureList() as $f) $out[$f['key']] = $saved[$f['key']] ?? true;
    return $out;
}
function deviceFeatures(array $dev): array {
    $own = json_decode($dev['features'] ?: '{}', true) ?: [];
    $out = defaultFeatures();
    foreach ($out as $k => $_) if (array_key_exists($k, $own)) $out[$k] = (bool)$own[$k];
    return $out;
}

function addEvent(string $dev, string $kind, string $msg): void {
    db()->prepare('INSERT INTO events (device_id, ts, kind, msg) VALUES (?, ?, ?, ?)')->execute([$dev, time(), $kind, mb_substr($msg, 0, 300)]);
    db()->prepare('DELETE FROM events WHERE device_id=? AND id NOT IN (SELECT id FROM events WHERE device_id=? ORDER BY id DESC LIMIT 200)')->execute([$dev, $dev]);
}

function jsonOut($data, int $code = 200): never {
    http_response_code($code); header('Content-Type: application/json'); echo json_encode($data); exit;
}

/** Último commit de una rama del repositorio (consulta a GitHub, cacheada 10 min). */
function branchHead(string $branch): string {
    $key = 'head_' . $branch; $ts = (int)cfg($key . '_ts');
    if (time() - $ts < 600 && cfg($key) !== '') return cfg($key);
    $ch = curl_init('https://api.github.com/repos/' . REPO . '/branches/' . $branch);
    $h = ['User-Agent: portaretrato-panel', 'Accept: application/vnd.github+json'];
    if (cfg('github_token') !== '') $h[] = 'Authorization: Bearer ' . cfg('github_token');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_HTTPHEADER => $h]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    $sha = '';
    if ($code === 200) $sha = substr((string)(json_decode($r, true)['commit']['sha'] ?? ''), 0, 7);
    setCfg($key, $sha); setCfg($key . '_ts', (string)time());
    return $sha;
}

/** Mueve la rama "stable" al commit actual de "main" (necesita un token de GitHub con permiso de escritura). */
function promote(): string {
    $tok = cfg('github_token'); if ($tok === '') return 'Falta configurar el token de GitHub en Ajustes.';
    $call = function ($method, $path, $body = null) use ($tok) {
        $ch = curl_init('https://api.github.com/repos/' . REPO . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['User-Agent: portaretrato-panel', 'Accept: application/vnd.github+json', 'Authorization: Bearer ' . $tok],
            CURLOPT_POSTFIELDS => $body ? json_encode($body) : null]);
        $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        return [$c, json_decode((string)$r, true)];
    };
    [$c, $main] = $call('GET', '/git/ref/heads/main');
    if ($c !== 200) return 'No pude leer main (HTTP ' . $c . ').';
    $sha = $main['object']['sha'];
    [$c] = $call('GET', '/git/ref/heads/stable');
    [$c2, $r] = $c === 200 ? $call('PATCH', '/git/refs/heads/stable', ['sha' => $sha, 'force' => false]) : $call('POST', '/git/refs', ['ref' => 'refs/heads/stable', 'sha' => $sha]);
    setCfg('head_stable_ts', '0'); setCfg('head_main_ts', '0');
    return $c2 < 300 ? '' : 'GitHub respondió ' . $c2 . ': ' . ($r['message'] ?? '');
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function ago(int $ts): string {
    if (!$ts) return 'nunca';
    $d = time() - $ts;
    if ($d < 90) return 'hace un momento';
    if ($d < 3600) return 'hace ' . intdiv($d, 60) . ' min';
    if ($d < 86400) return 'hace ' . intdiv($d, 3600) . ' h';
    return 'hace ' . intdiv($d, 86400) . ' d';
}
