<?php
// Reportar todos los errores pero no mostrarlos como HTML
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Los datos propios de cada instalación (clave de Gestión, base de datos, carpeta de
// álbumes) viven en backend/config.local.php, que NO se versiona. Ver config.local.sample.php.
$localConfig = __DIR__ . '/config.local.php';
if (!is_file($localConfig)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Falta backend/config.local.php']);
    exit;
}
require $localConfig;

try {
    $dsn = "mysql:host=" . (defined('DB_HOST') ? DB_HOST : '127.0.0.1') . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // Si falla la conexión, devolvemos un JSON con el error
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Error de conexión a la base de datos',
        'details' => $e->getMessage()
    ]);
    exit;
}
