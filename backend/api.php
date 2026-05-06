<?php
header('Content-Type: application/json');
// Activamos visualización para ver el error real si el try-catch falla
ini_set('display_errors', 1); 
error_reporting(E_ALL);

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
            echo json_encode($stmt->fetchAll());
            break;

        case 'get_album_settings':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT duracion_default, animacion_tipo FROM albums WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch());
            break;

        case 'update_album_settings':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? 0;
            $duracion = $data['duracion'] ?? 10;
            $animacion = $data['animacion'] ?? 'fade';
            
            $stmt = $pdo->prepare("UPDATE albums SET duracion_default = ?, animacion_tipo = ? WHERE id = ?");
            $stmt->execute([$duracion, $animacion, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'get_weather_settings':
            $stmt = $pdo->query("SELECT clave, valor FROM settings");
            $settings = [];
            foreach ($stmt->fetchAll() as $row) {
                $settings[$row['clave']] = $row['valor'];
            }
            echo json_encode($settings);
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
