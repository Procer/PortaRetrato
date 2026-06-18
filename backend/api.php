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

try {
    require_once 'config.php';

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'list_albums':
            $stmt = $pdo->query("SELECT id, nombre, activo, duracion_default, animacion_tipo FROM albums ORDER BY fecha_creacion DESC");
            echo json_encode($stmt->fetchAll());
            break;

        case 'get_active_media':
            $stmt = $pdo->query("SELECT m.*, a.duracion_default as album_duracion, a.animacion_tipo FROM media m JOIN albums a ON m.album_id = a.id WHERE a.activo = 1 ORDER BY m.orden ASC");
            sendCachedJson(json_encode($stmt->fetchAll()));
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

        case 'get_album_media':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM media WHERE album_id = ? ORDER BY orden ASC");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetchAll());
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
