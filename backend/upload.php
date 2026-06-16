<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_quick_show = (($_POST['quick_show'] ?? '0') === '1');

    if (!$is_quick_show) {
        $album_id = $_POST['album_id'] ?? 0;
        if (!$album_id) die(json_encode(['error' => 'ID de álbum no proporcionado']));
    }

    $upload_dir = $is_quick_show ? '../uploads/quick_show/' : '../uploads/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

    foreach ($_FILES['media']['name'] as $key => $name) {
        if ($_FILES['media']['error'][$key] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['media']['tmp_name'][$key];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $new_name = uniqid() . '.' . $ext;
            $destination = $upload_dir . $new_name;

            if (move_uploaded_file($tmp_name, $destination)) {
                if ($is_quick_show) {
                    $ruta = 'uploads/quick_show/' . $new_name;
                    $stmt = $pdo->prepare("INSERT INTO quick_show_media (ruta, tipo) VALUES (?, 'imagen')");
                    $stmt->execute([$ruta]);
                } else {
                    $type = in_array($ext, ['mp4', 'webm', 'ogg']) ? 'video' : 'imagen';
                    $stmt = $pdo->prepare("INSERT INTO media (album_id, ruta, tipo) VALUES (?, ?, ?)");
                    $stmt->execute([$album_id, 'uploads/' . $new_name, $type]);
                }
            }
        }
    }
    echo json_encode(['success' => true]);
}
?>
