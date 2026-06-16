<?php
// Reportar todos los errores pero no mostrarlos como HTML
error_reporting(E_ALL);
ini_set('display_errors', 0);

// CONTRASEÑA DE GESTIÓN — cambiá este valor antes de subir al servidor
define('GESTION_PASSWORD', 'portaretrato2025');

// CONFIGURACIÓN DE BASE DE DATOS DINÁMICA
// Detecta si estamos en localhost (Laragon) o en producción (Hosting)
if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['REMOTE_ADDR'] == '127.0.0.1') {
    // AJUSTES LOCALES (Laragon)
    $host = '127.0.0.1';
    $db   = 'portaretrato';
    $user = 'root';
    $pass = 'Catunga0112.';
} else {
    // AJUSTES DEL HOSTING (WNPower)
    // NOTA: Debes completar estos 3 campos con los datos que crees en tu cPanel
    $host = 'localhost'; 
    $db   = 'PONER_AQUI_NOMBRE_DB';   // Ejemplo: u123456_portaretrato
    $user = 'PONER_AQUI_USUARIO_DB';  // Ejemplo: u123456_admin
    $pass = 'PONER_AQUI_PASSWORD';    // La contraseña que asignes al usuario
}

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, $user, $pass, $options);

} catch (PDOException $e) {
    // Si falla la conexión, devolvemos un JSON con el error
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Error de conexión a la base de datos',
        'details' => $e->getMessage()
    ]);
    exit;
}
?>
