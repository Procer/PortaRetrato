<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $album_id = $_POST['album_id'] ?? 0;
    if (!$album_id) die(json_encode(['error' => 'ID de álbum no proporcionado']));

    $upload_dir = '../uploads/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

    foreach ($_FILES['media']['name'] as $key => $name) {
        if ($_FILES['media']['error'][$key] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['media']['tmp_name'][$key];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $new_name = uniqid() . '.' . $ext;
            $destination = $upload_dir . $new_name;

            if (move_uploaded_file($tmp_name, $destination)) {
                $type = in_array($ext, ['mp4', 'webm', 'ogg']) ? 'video' : 'imagen';
                $stmt = $pdo->prepare("INSERT INTO media (album_id, ruta, tipo) VALUES (?, ?, ?)");
                $stmt->execute([$album_id, 'uploads/' . $new_name, $type]);
            }
        }
    }
    echo json_encode(['success' => true]);
}
?>
