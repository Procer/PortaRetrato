<?php
// Genera las miniaturas (400 px) de las imágenes que ya estaban subidas.
// Uso: desde Gestión logueado abrir /backend/backfill_thumbs.php, o por CLI:
//      php backend/backfill_thumbs.php
// Es idempotente: salta lo que ya tiene miniatura. Los videos se resuelven solos
// la primera vez que se abren en Gestión (ver healVideoPoster en gestion.js).
require_once 'bootstrap.php';
require_once 'config.php';
if (PHP_SAPI !== 'cli') { header('Content-Type: text/plain; charset=utf-8'); requireAuth(); }
@set_time_limit(0);

$root = dirname(__DIR__);
$rutas = array_merge(
    $pdo->query("SELECT ruta FROM media WHERE tipo = 'imagen'")->fetchAll(PDO::FETCH_COLUMN),
    $pdo->query("SELECT ruta FROM quick_show_media WHERE tipo = 'imagen'")->fetchAll(PDO::FETCH_COLUMN)
);

$hechas = $saltadas = $fallidas = 0;
foreach ($rutas as $ruta) {
    $dest = $root . '/' . thumbRelPath($ruta);
    if (is_file($dest)) { $saltadas++; continue; }
    if (makeThumb($root . '/' . $ruta, $dest)) $hechas++; else { $fallidas++; echo "falló: $ruta\n"; }
}
echo "Miniaturas creadas: $hechas, ya existían: $saltadas, fallidas: $fallidas\n";
