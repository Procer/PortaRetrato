<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
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

try {
    require_once 'config.php';

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'list_albums':
            $stmt = $pdo->query("SELECT id, nombre, activo, duracion_default, animacion_tipo FROM albums ORDER BY fecha_creacion DESC");
            echo json_encode($stmt->fetchAll());
            break;

        case 'get_active_media':
            // Desempate por id: sin él, filas con el mismo "orden" (p.ej. fotos
            // nunca reordenadas a mano, todas en 0) pueden volver en distinto
            // orden entre consultas y el Visor termina repitiendo/saltando fotos.
            $stmt = $pdo->query("SELECT m.*, a.duracion_default as album_duracion, a.animacion_tipo FROM media m JOIN albums a ON m.album_id = a.id WHERE a.activo = 1 ORDER BY m.orden ASC, m.id ASC");
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) { $row['ruta'] = bustCache($row['ruta']); }
            unset($row);
            sendCachedJson(json_encode($rows));
            break;

        case 'get_sync_manifest':
            // Todo lo que el Visor necesita para funcionar 100% offline, en un
            // solo JSON. El Visor lo compara contra lo que ya tiene cacheado
            // (por version_hash) y solo descarga del hosting los archivos
            // nuevos o modificados. Es la base del "modo sync": cada foto/video
            // viaja una única vez.
            $stmt = $pdo->query("SELECT m.id, m.album_id, m.ruta, m.tipo, m.orden, m.duracion_img, a.duracion_default AS album_duracion, a.animacion_tipo FROM media m JOIN albums a ON m.album_id = a.id WHERE a.activo = 1 ORDER BY m.orden ASC, m.id ASC");
            $media = [];
            foreach ($stmt->fetchAll() as $row) {
                $full = '../' . $row['ruta'];
                $row['bytes'] = @filesize($full) ?: 0;
                $row['mtime'] = @filemtime($full) ?: 0;
                $row['ruta']  = bustCache($row['ruta']);
                $media[] = $row;
            }

            $settings = [];
            foreach ($pdo->query("SELECT clave, valor FROM settings")->fetchAll() as $row) {
                $settings[$row['clave']] = $row['valor'];
            }
            // La imagen de reposo también se sirve desde uploads/ → el Service
            // Worker la cachea igual que cualquier foto.
            if (!empty($settings['visor_off_image'])) {
                $settings['visor_off_image'] = bustCache($settings['visor_off_image']);
            }

            $recordatorios = $pdo->query("SELECT * FROM recordatorios ORDER BY fecha_creacion DESC")->fetchAll();
            $quick_show    = $pdo->query("SELECT * FROM quick_show_media ORDER BY orden ASC, fecha_subida ASC")->fetchAll();

            $payload = [
                'media'         => $media,
                'settings'      => $settings,
                'recordatorios' => $recordatorios,
                'quick_show'    => $quick_show,
            ];
            $payload['version_hash'] = md5(json_encode($payload));
            sendCachedJson(json_encode($payload));
            break;

        case 'get_album_settings':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT duracion_default, animacion_tipo FROM albums WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch());
            break;

        case 'update_album_settings':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            $duracion = (isset($data['duracion']) && (int)$data['duracion'] > 0) ? (int)$data['duracion'] : null;
            $animacion = (isset($data['animacion']) && $data['animacion'] !== '') ? $data['animacion'] : null;
            $stmt = $pdo->prepare("UPDATE albums SET duracion_default = ?, animacion_tipo = ? WHERE id = ?");
            $stmt->execute([$duracion, $animacion, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'update_media_duration':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            $duracion = (isset($data['duracion']) && (int)$data['duracion'] > 0) ? (int)$data['duracion'] : null;
            $stmt = $pdo->prepare("UPDATE media SET duracion_img = ? WHERE id = ?");
            $stmt->execute([$duracion, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'get_weather_settings':
            $stmt = $pdo->query("SELECT clave, valor FROM settings");
            $settings = [];
            foreach ($stmt->fetchAll() as $row) {
                $settings[$row['clave']] = $row['valor'];
            }
            sendCachedJson(json_encode($settings));
            break;

        case 'update_weather_settings':
            $data = json_decode(file_get_contents('php://input'), true);
            foreach ($data as $key => $value) {
                // Usamos REPLACE INTO para MySQL para que inserte si no existe o actualice si ya existe
                $stmt = $pdo->prepare("REPLACE INTO settings (clave, valor) VALUES (?, ?)");
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

        case 'get_album_media':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM media WHERE album_id = ? ORDER BY orden ASC, id ASC");
            $stmt->execute([$id]);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) { $row['ruta'] = bustCache($row['ruta']); }
            unset($row);
            echo json_encode($rows);
            break;

        case 'rotate_media':
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
            echo json_encode(['success' => true]);
            break;

        case 'set_active_album':
            $id = $_GET['id'] ?? 0;
            $pdo->query("UPDATE albums SET activo = 0");
            $stmt = $pdo->prepare("UPDATE albums SET activo = 1 WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'reorder_media':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("UPDATE media SET orden = ? WHERE id = ?");
            foreach ($data as $item) {
                $stmt->execute([(int)$item['orden'], (int)$item['id']]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete_media':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT ruta FROM media WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                $full_path = '../' . $row['ruta'];
                if (file_exists($full_path)) unlink($full_path);
                $stmt = $pdo->prepare("DELETE FROM media WHERE id = ?");
                $stmt->execute([$id]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'create_album':
            $data = json_decode(file_get_contents('php://input'), true);
            $nombre = $data['nombre'] ?? 'Nuevo Album';
            $stmt = $pdo->prepare("INSERT INTO albums (nombre) VALUES (?)");
            $stmt->execute([$nombre]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'empty_album':
            $id = $_GET['id'] ?? 0;
            // Obtener rutas de archivos para borrarlos físicamente
            $stmt = $pdo->prepare("SELECT ruta FROM media WHERE album_id = ?");
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll() as $row) {
                $full_path = '../' . $row['ruta'];
                if (file_exists($full_path)) unlink($full_path);
            }
            $stmt = $pdo->prepare("DELETE FROM media WHERE album_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'delete_album':
            $id = $_GET['id'] ?? 0;
            // Primero vaciamos para borrar archivos físicos
            $stmt = $pdo->prepare("SELECT ruta FROM media WHERE album_id = ?");
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll() as $row) {
                $full_path = '../' . $row['ruta'];
                if (file_exists($full_path)) unlink($full_path);
            }
            // Borrar el álbum (el CASCADE de la BD borraría los registros de media)
            $stmt = $pdo->prepare("DELETE FROM albums WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'get_quick_show_media':
            $stmt = $pdo->query("SELECT * FROM quick_show_media ORDER BY orden ASC, fecha_subida ASC");
            sendCachedJson(json_encode($stmt->fetchAll()));
            break;

        case 'delete_quick_show_media':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT ruta FROM quick_show_media WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                $full_path = '../' . $row['ruta'];
                if (file_exists($full_path)) unlink($full_path);
                $stmt = $pdo->prepare("DELETE FROM quick_show_media WHERE id = ?");
                $stmt->execute([$id]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'clear_quick_show':
            $stmt = $pdo->query("SELECT ruta FROM quick_show_media");
            foreach ($stmt->fetchAll() as $row) {
                $full_path = '../' . $row['ruta'];
                if (file_exists($full_path)) unlink($full_path);
            }
            $pdo->query("DELETE FROM quick_show_media");
            echo json_encode(['success' => true]);
            break;

        case 'update_quick_show_media':
            $id         = (int)($_POST['id'] ?? 0);
            $dia_semana = (int)($_POST['dia_semana'] ?? 1);
            $horario    = $_POST['horario'] ?? '08:00';
            if ($id <= 0) { echo json_encode(['error' => 'ID inválido']); break; }
            if ($dia_semana < 0 || $dia_semana > 6) { echo json_encode(['error' => 'Día inválido']); break; }
            if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horario)) { echo json_encode(['error' => 'Horario inválido']); break; }
            $stmt = $pdo->prepare("UPDATE quick_show_media SET dia_semana = ?, horario = ? WHERE id = ?");
            $stmt->execute([$dia_semana, $horario, $id]);
            echo json_encode(['success' => true]);
            break;

        // ─── RECORDATORIOS FAMILIARES ──────────────────────────────────────
        case 'get_recordatorios':
            $stmt = $pdo->query("SELECT * FROM recordatorios ORDER BY fecha_creacion DESC");
            sendCachedJson(json_encode($stmt->fetchAll()));
            break;

        case 'add_recordatorio':
            $data = json_decode(file_get_contents('php://input'), true);
            $mensaje = trim($data['mensaje'] ?? '');
            $autor = trim($data['autor'] ?? '') ?: null;
            if ($mensaje === '') { echo json_encode(['error' => 'El mensaje no puede estar vacío']); break; }
            $mensaje = mb_substr($mensaje, 0, 280);
            $stmt = $pdo->prepare("INSERT INTO recordatorios (mensaje, autor) VALUES (?, ?)");
            $stmt->execute([$mensaje, $autor]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'delete_recordatorio':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM recordatorios WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'mark_recordatorios_vistos':
            $pdo->query("UPDATE recordatorios SET visto = 1 WHERE visto = 0");
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
?>
