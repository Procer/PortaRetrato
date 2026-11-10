<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Responde con ETag; si el cliente ya tiene la versión actual devuelve 304 sin cuerpo.
function sendCachedJson(string $json): void {
    $etag = '"' . md5($json) . '"';
    header("ETag: $etag");
    header("Cache-Control: no-cache");
    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
    echo $json;
}

// Agrega un query param con el mtime del archivo para invalidar el caché
// del navegador (uploads/ se sirve con Cache-Control immutable 30 días)
// cuando el archivo se sobreescribe, p.ej. al rotar una imagen.
function bustCache(string $ruta): string {
    $mtime = @filemtime('../' . $ruta);
    return $mtime ? $ruta . '?v=' . $mtime : $ruta;
}

// Rota una imagen ya subida, sobrescribiendo el archivo en el mismo formato.
function rotateImageFile(string $fullPath, int $degrees): bool {
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $creators = [
        'jpg'  => 'imagecreatefromjpeg',
        'jpeg' => 'imagecreatefromjpeg',
        'png'  => 'imagecreatefrompng',
        'gif'  => 'imagecreatefromgif',
        'webp' => 'imagecreatefromwebp',
    ];
    $creator = $creators[$ext] ?? null;
    if (!$creator || !function_exists($creator)) return false;

    $src = $creator($fullPath);
    if (!$src) return false;

    // imagerotate() gira antihorario; invertimos el ángulo para que "90" sea horario
    $rotated = imagerotate($src, 360 - $degrees, 0);
    imagedestroy($src);
    if (!$rotated) return false;

    if ($ext === 'webp' || $ext === 'png') {
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);
    }

    switch (true) {
        case $ext === 'webp' && function_exists('imagewebp'):
            $result = imagewebp($rotated, $fullPath, 82);
            break;
        case $ext === 'png':
            $result = imagepng($rotated, $fullPath);
            break;
        case $ext === 'gif':
            $result = imagegif($rotated, $fullPath);
            break;
        default:
            $result = imagejpeg($rotated, $fullPath, 85);
    }
    imagedestroy($rotated);
    return (bool)$result;
}

// Syncthing: API REST local (127.0.0.1:8384). La clave se lee de su config.xml.
function syncthingCall(string $method, string $path, $body = null) {
    static $key = false;
    if ($key === false) {
        $key = null;
        $home = function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['dir'] ?? '') : '';
        foreach (["$home/.local/state/syncthing/config.xml", "$home/.config/syncthing/config.xml"] as $f) {
            if (is_file($f) && preg_match('#<apikey>([^<]+)</apikey>#', file_get_contents($f), $k)) { $key = $k[1]; break; }
        }
    }
    if (!$key) return null;
    $ch = curl_init('http://127.0.0.1:8384/rest/' . $path);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_CUSTOMREQUEST => $method,
             CURLOPT_HTTPHEADER => ['X-API-Key: ' . $key, 'Content-Type: application/json']];
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    curl_setopt_array($ch, $opts);
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($r === false || $code >= 400) return null;
    return $r === '' ? true : (json_decode($r, true) ?? true);
}

// ID de este equipo, celulares emparejados y pedidos de emparejamiento pendientes.
function syncthingInfo(): ?array {
    $status = syncthingCall('GET', 'system/status');
    if (!is_array($status) || empty($status['myID'])) return null;
    $conns = syncthingCall('GET', 'system/connections')['connections'] ?? [];
    $devices = [];
    foreach (syncthingCall('GET', 'config/devices') ?: [] as $d) {
        if ($d['deviceID'] === $status['myID']) continue;
        $devices[] = ['id' => $d['deviceID'], 'nombre' => $d['name'] ?: substr($d['deviceID'], 0, 7), 'conectado' => !empty($conns[$d['deviceID']]['connected'])];
    }
    $pend = [];
    $known = array_column($devices, 'id');
    foreach (syncthingCall('GET', 'cluster/pending/devices') ?: [] as $id => $info) {
        if (!in_array($id, $known, true)) $pend[] = ['id' => $id, 'nombre' => $info['name'] ?? ''];
    }
    return ['id' => $status['myID'], 'dispositivos' => $devices, 'pendientes' => $pend];
}

try {
    require_once 'bootstrap.php';
    require_once 'config.php';
    require_once 'sync_lib.php';
    $action = $_GET['action'] ?? '';

    // Lo que lee el Visor (esta misma PC) es público dentro de la red; todo lo demás exige sesión de Gestión.
    $publicActions = ['get_visor_data', 'get_quick_show_media', 'get_recordatorios', 'mark_recordatorios_vistos', 'hija_list', 'hija_send', 'estado_red'];
    // Mientras el equipo tiene su propio WiFi abierto (modo ap), quien esté conectado a él
    // puede elegir la red de la casa sin sesión; el WiFi propio tiene clave y solo la muestra la pantalla.
    if (in_array($action, ['wifi_scan', 'wifi_connect'], true) && redEstado()['modo'] === 'ap') $publicActions[] = $action;
    if (!in_array($action, $publicActions, true)) requireAuth();

    switch ($action) {
        // ─── VISOR ─────────────────────────────────────────────────────────
        case 'get_visor_data':
            // Todo lo que el Visor necesita en un solo JSON chico: media de los álbumes
            // activos, settings, mensajes y Pase Rápido. version_hash permite saber si algo cambió.
            $stmt = $pdo->query("SELECT m.id, m.album_id, m.ruta, m.tipo, m.orden, m.duracion_img, a.duracion_default AS album_duracion, a.animacion_tipo FROM media m JOIN albums a ON m.album_id = a.id WHERE a.activo = 1 ORDER BY m.orden ASC, m.id ASC");
            $media = $stmt->fetchAll();
            foreach ($media as &$row) { $row['ruta'] = bustCache($row['ruta']); }
            unset($row);

            $settings = [];
            foreach ($pdo->query("SELECT clave, valor FROM settings WHERE clave NOT IN ('gestion_pass_hash', 'clave_inicial')")->fetchAll() as $row) {
                $settings[$row['clave']] = $row['valor'];
            }
            if (!empty($settings['visor_off_image'])) {
                $settings['visor_off_image'] = bustCache($settings['visor_off_image']);
            }

            $quick_show = $pdo->query("SELECT * FROM quick_show_media ORDER BY orden ASC, fecha_subida ASC")->fetchAll();
            foreach ($quick_show as &$q) { $q['ruta'] = bustCache($q['ruta']); }
            unset($q);

            $payload = [
                'media'         => $media,
                'settings'      => $settings,
                'recordatorios' => $pdo->query("SELECT * FROM recordatorios ORDER BY fecha_creacion DESC")->fetchAll(),
                'quick_show'    => $quick_show,
            ];
            $payload['version_hash'] = md5(json_encode($payload));
            sendCachedJson(json_encode($payload));
            break;

        case 'get_quick_show_media':
            $rows = $pdo->query("SELECT * FROM quick_show_media ORDER BY orden ASC, fecha_subida ASC")->fetchAll();
            foreach ($rows as &$row) {
                $row['thumb'] = thumbIfExists($row['ruta']);
                $row['ruta']  = bustCache($row['ruta']);
            }
            unset($row);
            sendCachedJson(json_encode($rows));
            break;

        case 'get_recordatorios':
            sendCachedJson(json_encode($pdo->query("SELECT * FROM recordatorios ORDER BY fecha_creacion DESC")->fetchAll()));
            break;

        case 'mark_recordatorios_vistos':
            $pdo->query("UPDATE recordatorios SET visto = 1 WHERE visto = 0");
            echo json_encode(['success' => true]);
            break;

        // ─── SECCIÓN DE LA HIJA (/hija/): solo leer y escribir mensajes ────
        case 'hija_list':
            header('Cache-Control: no-store');
            echo json_encode($pdo->query("SELECT id, mensaje, autor, de_hija, fecha_creacion FROM recordatorios ORDER BY fecha_creacion DESC, id DESC LIMIT 40")->fetchAll());
            break;

        case 'hija_send':
            if (!featOn($pdo, 'mensajes')) { echo json_encode(['error' => 'Esta función está desactivada']); break; }
            $data = json_decode(file_get_contents('php://input'), true);
            $mensaje = mb_substr(trim($data['mensaje'] ?? ''), 0, 280);
            if ($mensaje === '') { echo json_encode(['error' => 'Escribí algo primero']); break; }
            // Freno simple: máx. 10 mensajes por minuto, por si el celular se traba reenviando.
            if ((int)$pdo->query("SELECT COUNT(*) FROM recordatorios WHERE de_hija = 1 AND fecha_creacion > (NOW() - INTERVAL 1 MINUTE)")->fetchColumn() >= 10) {
                echo json_encode(['error' => 'Esperá un momento']); break;
            }
            $pdo->prepare("INSERT INTO recordatorios (mensaje, autor, de_hija) VALUES (?, ?, 1)")->execute([$mensaje, getSetting($pdo, 'familiar_nombre', defined('HIJA_NOMBRE') ? HIJA_NOMBRE : 'Hija') ?: 'Hija']);
            echo json_encode(['success' => true]);
            break;

        // ─── RED, PRIMER USO Y SISTEMA ─────────────────────────────────────
        case 'estado_red':
            // Lo lee el Visor: ayuda en pantalla cuando no hay red o es el primer uso.
            header('Cache-Control: no-store');
            $r = redEstado();
            $primer = getSetting($pdo, 'setup_done', '0') !== '1';
            echo json_encode([
                'modo'          => $r['modo'],
                'setup'         => $r['setup'],
                'error'         => $r['error'],
                'primer_uso'    => $primer,
                'clave_inicial' => $primer ? getSetting($pdo, 'clave_inicial') : '',
                'host'          => gethostname(),
                'ips'           => array_values(array_filter(preg_split('/\s+/', trim((string)@shell_exec('hostname -I 2>/dev/null'))))),
            ]);
            break;

        case 'wifi_status':
            echo json_encode(prAdmin(['wifi-status']));
            break;

        case 'wifi_scan':
            echo json_encode(prAdmin(['wifi-scan']));
            break;

        case 'wifi_connect':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $ssid = trim((string)($data['ssid'] ?? ''));
            $pass = (string)($data['clave'] ?? '');
            if ($ssid === '' || preg_match('/[\r\n]/', $ssid . $pass)) { echo json_encode(['error' => 'Elegí una red']); break; }
            echo json_encode(prAdmin(['wifi-connect'], $ssid . "\n" . $pass . "\n"));
            break;

        case 'sys_action':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $what = (string)($data['accion'] ?? '');
            $val  = (string)($data['valor'] ?? '');
            $map = ['reiniciar' => ['reboot'], 'apagar' => ['poweroff'], 'reabrir_visor' => ['restart-kiosk'],
                    'olvidar_wifi' => ['wifi-forget'], 'zona' => ['set-timezone', $val], 'rotar' => ['set-rotate', $val]];
            if (!isset($map[$what])) { echo json_encode(['error' => 'Acción desconocida']); break; }
            if ($what === 'zona') setSetting($pdo, 'timezone', $val);
            if ($what === 'rotar') setSetting($pdo, 'rotate', $val);
            echo json_encode(prAdmin($map[$what]));
            break;

        case 'change_password':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $new = (string)($data['nueva'] ?? '');
            $isFirst = getSetting($pdo, 'setup_done', '0') !== '1';
            if (!$isFirst && !checkGestionPassword($pdo, (string)($data['actual'] ?? ''))) { echo json_encode(['error' => 'La clave actual no es correcta']); break; }
            if (mb_strlen($new) < 6) { echo json_encode(['error' => 'La clave nueva debe tener al menos 6 caracteres']); break; }
            setSetting($pdo, 'gestion_pass_hash', password_hash($new, PASSWORD_DEFAULT));
            setSetting($pdo, 'clave_inicial', '');
            echo json_encode(['success' => true]);
            break;

        case 'setup_done':
            setSetting($pdo, 'setup_done', '1');
            setSetting($pdo, 'clave_inicial', '');
            echo json_encode(['success' => true]);
            break;
        // ─── ESTADO DEL EQUIPO ─────────────────────────────────────────────
        case 'get_status':
            $dir = is_dir(syncDir()) ? syncDir() : projectRoot();
            $cnt = $pdo->query("SELECT SUM(tipo='imagen') AS fotos, SUM(tipo='video') AS videos FROM media")->fetch();
            $ips = array_values(array_filter(preg_split('/\s+/', trim((string)@shell_exec('hostname -I 2>/dev/null')))));
            echo json_encode([
                'albumes'      => (int)$pdo->query("SELECT COUNT(*) FROM albums")->fetchColumn(),
                'fotos'        => (int)($cnt['fotos'] ?? 0),
                'videos'       => (int)($cnt['videos'] ?? 0),
                'pendientes'   => pendingImportCount($pdo),
                'disco_libre'  => (int)@disk_free_space($dir),
                'disco_total'  => (int)@disk_total_space($dir),
                'host'         => gethostname(),
                'ips'          => $ips,
                'syncthing'    => syncthingInfo(),
            ]);
            break;

        case 'syncthing_accept':
            // Acepta un celular que pidió emparejarse y le comparte la carpeta de fotos.
            $data = json_decode(file_get_contents('php://input'), true);
            $id = preg_replace('/[^A-Z0-9-]/', '', strtoupper($data['id'] ?? ''));
            if ($id === '') { echo json_encode(['error' => 'ID inválido']); break; }
            $nombre = trim($data['nombre'] ?? '') ?: 'Celular';
            $ok = syncthingCall('PUT', 'config/devices/' . $id, ['deviceID' => $id, 'name' => $nombre, 'autoAcceptFolders' => false]);
            $folder = syncthingCall('GET', 'config/folders/fotos');
            if ($ok && is_array($folder)) {
                $ids = array_column($folder['devices'], 'deviceID');
                if (!in_array($id, $ids, true)) {
                    $folder['devices'][] = ['deviceID' => $id];
                    $ok = syncthingCall('PUT', 'config/folders/fotos', $folder);
                }
            }
            echo json_encode($ok ? ['success' => true] : ['error' => 'No se pudo aceptar el dispositivo']);
            break;

        case 'syncthing_remove':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = preg_replace('/[^A-Z0-9-]/', '', strtoupper($data['id'] ?? ''));
            $ok = $id !== '' && syncthingCall('DELETE', 'config/devices/' . $id);
            echo json_encode($ok ? ['success' => true] : ['error' => 'No se pudo quitar el dispositivo']);
            break;

        case 'run_import':
            spawnImport();
            echo json_encode(['success' => true]);
            break;

        // ─── ÁLBUMES ───────────────────────────────────────────────────────
        case 'list_albums':
            $rows = $pdo->query(
                "SELECT a.id, a.nombre, a.carpeta, a.activo, a.duracion_default, a.animacion_tipo,
                        COUNT(m.id) AS total, COALESCE(SUM(m.tipo = 'video'), 0) AS videos,
                        (SELECT m2.ruta FROM media m2 WHERE m2.album_id = a.id ORDER BY m2.orden ASC, m2.id ASC LIMIT 1) AS portada
                 FROM albums a LEFT JOIN media m ON m.album_id = a.id
                 GROUP BY a.id ORDER BY a.activo DESC, a.fecha_creacion DESC"
            )->fetchAll();
            foreach ($rows as &$row) {
                $row['portada'] = $row['portada'] ? (thumbIfExists($row['portada']) ?: bustCache($row['portada'])) : null;
            }
            unset($row);
            echo json_encode($rows);
            break;

        case 'get_album_media':
            $stmt = $pdo->prepare("SELECT * FROM media WHERE album_id = ? ORDER BY orden ASC, id ASC");
            $stmt->execute([(int)($_GET['id'] ?? 0)]);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) {
                $row['thumb'] = thumbIfExists($row['ruta']);
                $row['ruta']  = bustCache($row['ruta']);
            }
            unset($row);
            echo json_encode($rows);
            break;

        case 'create_album':
            $data = json_decode(file_get_contents('php://input'), true);
            $nombre = trim($data['nombre'] ?? '') ?: 'Nuevo Album';
            $base = $carpeta = safeFolderName($nombre);
            $existe = $pdo->prepare("SELECT COUNT(*) FROM albums WHERE carpeta = ?");
            for ($n = 2; ; $n++) {
                $existe->execute([$carpeta]);
                if (!$existe->fetchColumn() && !is_dir(syncDir() . '/' . $carpeta)) break;
                $carpeta = "$base $n";
            }
            if (syncFolderReady()) @mkdir(syncDir() . '/' . $carpeta, 0755, true);
            $pdo->prepare("INSERT INTO albums (nombre, carpeta) VALUES (?, ?)")->execute([$nombre, $carpeta]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'empty_album':
        case 'delete_album':
            // Borra también los originales de la carpeta sincronizada (Syncthing los quita del celular).
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, ruta, origen FROM media WHERE album_id = ?");
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll() as $row) removeMediaRow($pdo, $row, true);
            if ($action === 'delete_album') {
                $c = $pdo->prepare("SELECT carpeta FROM albums WHERE id = ?");
                $c->execute([$id]);
                $carpeta = $c->fetchColumn();
                // La carpeta va a la papelera completa (incluye lo que no era foto/video).
                if ($carpeta && syncFolderReady() && is_dir(syncDir() . '/' . $carpeta)) {
                    $dest = trashDir() . '/' . date('Ymd-His') . '-album/' . $carpeta;
                    @mkdir(dirname($dest), 0755, true);
                    @rename(syncDir() . '/' . $carpeta, $dest);
                }
                $pdo->prepare("DELETE FROM albums WHERE id = ?")->execute([$id]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'set_active_album':
            $id = (int)($_GET['id'] ?? 0);
            $pdo->query("UPDATE albums SET activo = 0");
            $pdo->prepare("UPDATE albums SET activo = 1 WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'update_album_settings':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            $duracion = (isset($data['duracion']) && (int)$data['duracion'] > 0) ? (int)$data['duracion'] : null;
            $animacion = (isset($data['animacion']) && $data['animacion'] !== '') ? $data['animacion'] : null;
            $pdo->prepare("UPDATE albums SET duracion_default = ?, animacion_tipo = ? WHERE id = ?")->execute([$duracion, $animacion, $id]);
            echo json_encode(['success' => true]);
            break;

        // ─── FOTOS Y VIDEOS ────────────────────────────────────────────────
        case 'update_media_duration':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            $duracion = (isset($data['duracion']) && (int)$data['duracion'] > 0) ? (int)$data['duracion'] : null;
            $pdo->prepare("UPDATE media SET duracion_img = ? WHERE id = ?")->execute([$duracion, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'rotate_media':
            // Gira solo la copia que muestra el Visor; el original del celular queda intacto.
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            $degrees = (int)($data['degrees'] ?? 90);
            if (!in_array($degrees, [90, 180, 270], true)) {
                echo json_encode(['error' => 'Ángulo inválido']);
                break;
            }
            $stmt = $pdo->prepare("SELECT ruta, tipo FROM media WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row || $row['tipo'] !== 'imagen') {
                echo json_encode(['error' => 'Imagen no encontrada']);
                break;
            }
            $fullPath = '../' . $row['ruta'];
            if (!file_exists($fullPath) || !rotateImageFile($fullPath, $degrees)) {
                echo json_encode(['error' => 'No se pudo rotar la imagen']);
                break;
            }
            makeThumb($fullPath, '../' . thumbRelPath($row['ruta']));
            echo json_encode(['success' => true]);
            break;

        case 'delete_media':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, ruta, origen FROM media WHERE id = ?");
            $stmt->execute([$id]);
            if ($row = $stmt->fetch()) removeMediaRow($pdo, $row, true);
            echo json_encode(['success' => true]);
            break;

        // ─── AJUSTES (reloj, clima, slide, modo noche, horario) ────────────
        case 'get_settings':
            $settings = [];
            foreach ($pdo->query("SELECT clave, valor FROM settings WHERE clave NOT IN ('gestion_pass_hash', 'clave_inicial')")->fetchAll() as $row) {
                $settings[$row['clave']] = $row['valor'];
            }
            echo json_encode($settings);
            break;

        case 'update_settings':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("REPLACE INTO settings (clave, valor) VALUES (?, ?)");
            foreach ($data as $key => $value) {
                if ($key === 'visor_off_image') continue;
                if (strpos($key, 'feat_') === 0 || strpos($key, 'panel_') === 0 || $key === 'gestion_pass_hash' || $key === 'clave_inicial') continue;   // los maneja el panel / sus propias acciones   // se cambia solo con la subida / clear_rest_image
                $stmt->execute([$key, $value]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'clear_rest_image':
            $cur = $pdo->query("SELECT valor FROM settings WHERE clave = 'visor_off_image'")->fetchColumn();
            if ($cur) {
                $p = '../' . preg_replace('/\?.*$/', '', $cur);
                if (file_exists($p)) @unlink($p);
            }
            $pdo->prepare("REPLACE INTO settings (clave, valor) VALUES ('visor_off_image', '')")->execute();
            echo json_encode(['success' => true]);
            break;

        // ─── PASE RÁPIDO ───────────────────────────────────────────────────
        case 'delete_quick_show_media':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT ruta FROM quick_show_media WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                $full_path = '../' . $row['ruta'];
                if (file_exists($full_path)) @unlink($full_path);
                deleteThumb($row['ruta']);
                $pdo->prepare("DELETE FROM quick_show_media WHERE id = ?")->execute([$id]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'clear_quick_show':
            foreach ($pdo->query("SELECT ruta FROM quick_show_media")->fetchAll() as $row) {
                $full_path = '../' . $row['ruta'];
                if (file_exists($full_path)) @unlink($full_path);
                deleteThumb($row['ruta']);
            }
            $pdo->query("DELETE FROM quick_show_media");
            echo json_encode(['success' => true]);
            break;

        case 'update_quick_show_media':
            $data       = json_decode(file_get_contents('php://input'), true) ?: [];
            $id         = (int)($data['id'] ?? 0);
            $dia_semana = (int)($data['dia_semana'] ?? 1);
            $horario    = $data['horario'] ?? '08:00';
            if ($id <= 0) { echo json_encode(['error' => 'ID inválido']); break; }
            if ($dia_semana < 0 || $dia_semana > 6) { echo json_encode(['error' => 'Día inválido']); break; }
            if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horario)) { echo json_encode(['error' => 'Horario inválido']); break; }
            $pdo->prepare("UPDATE quick_show_media SET dia_semana = ?, horario = ? WHERE id = ?")->execute([$dia_semana, $horario, $id]);
            echo json_encode(['success' => true]);
            break;

        // ─── MENSAJES FAMILIARES ───────────────────────────────────────────
        case 'add_recordatorio':
            $data = json_decode(file_get_contents('php://input'), true);
            $mensaje = trim($data['mensaje'] ?? '');
            $autor = trim($data['autor'] ?? '') ?: null;
            if ($mensaje === '') { echo json_encode(['error' => 'El mensaje no puede estar vacío']); break; }
            $mensaje = mb_substr($mensaje, 0, 280);
            $pdo->prepare("INSERT INTO recordatorios (mensaje, autor) VALUES (?, ?)")->execute([$mensaje, $autor]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'delete_recordatorio':
            $pdo->prepare("DELETE FROM recordatorios WHERE id = ?")->execute([(int)($_GET['id'] ?? 0)]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Acción no válida']);
            break;
    }
} catch (Throwable $e) {
    // Si llegamos aquí, enviamos el error como JSON
    echo json_encode([
        'error' => 'Error fatal en el script',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
