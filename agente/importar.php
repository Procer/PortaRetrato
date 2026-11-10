<?php
// Importador de la carpeta sincronizada. Se ejecuta por consola, cada minuto y al iniciar:
//   php agente/importar.php
// Registra en la BD los archivos nuevos de <SYNC_DIR>/<Álbum>/, genera sus copias y
// miniaturas, y quita lo que ya no está. Log en agente/importar.log.

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

// config.php decide la BD por HTTP_HOST/REMOTE_ADDR; por consola siempre es la local.
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
ini_set('memory_limit', '1024M');
set_time_limit(0);

$lock = fopen(sys_get_temp_dir() . '/portaretrato-importar.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit(0);   // ya hay una corrida en curso

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/sync_lib.php';

$logFile = __DIR__ . '/importar.log';
$lines = [];
$log = function (string $m) use (&$lines) { $lines[] = '[' . date('Y-m-d H:i:s') . '] ' . $m; };

try {
    $st = importSyncFolder($pdo, $log);
    if ($st['nuevos'] || $st['actualizados'] || $st['borrados'] || $st['fallos'] || $st['albumes']) {
        $log(sprintf('Listo: %d nuevos, %d actualizados, %d quitados, %d fallos', $st['nuevos'], $st['actualizados'], $st['borrados'], $st['fallos']));
    }
} catch (Throwable $e) {
    $log('ERROR: ' . $e->getMessage());
}

if ($lines) {
    if (is_file($logFile) && filesize($logFile) > 512 * 1024) @rename($logFile, $logFile . '.old');
    file_put_contents($logFile, implode("\n", $lines) . "\n", FILE_APPEND);
    echo implode("\n", $lines) . "\n";
}
