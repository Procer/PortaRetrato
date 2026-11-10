<?php
require_once '../backend/config.php';
session_start();
if (!isset($_SESSION['gestion_auth']) || $_SESSION['gestion_auth'] !== true) {
    header('Location: login.php');
    exit;
}
$v = max(@filemtime('../assets/js/gestion.js'), @filemtime('../assets/css/gestion.css'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#5b5bf0">
    <meta name="color-scheme" content="light dark">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Aura">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="icons/icon-192.png">
    <title>Aura</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop offset='0' stop-color='%235b5bf0'/%3E%3Cstop offset='1' stop-color='%238b5cf6'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='64' height='64' rx='16' fill='url(%23g)'/%3E%3Crect x='14' y='18' width='36' height='28' rx='5' fill='none' stroke='white' stroke-width='4'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="../assets/css/gestion.css?v=<?= $v ?>">
</head>
<body>
    <div class="app">
        <main class="main" id="main"></main>
        <nav class="tabbar" id="tabbar"></nav>
    </div>
    <div id="layer"></div>
    <div class="toasts" id="toasts"></div>
    <script src="../assets/vendor/qrcode.js"></script>
    <script src="../assets/js/gestion.js?v=<?= $v ?>"></script>
</body>
</html>
