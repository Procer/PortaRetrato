<?php
// Importador de la carpeta sincronizada. Se ejecuta por consola (cada minuto desde systemd,
// y tras cada subida desde Gestión):
//   php agente/importar.php
// Registra en la BD los archivos nuevos de <SYNC_DIR>/<Álbum>/, genera sus copias y
// miniaturas, y quita lo que ya no está. Log en agente/importar.log.

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

ini_set('memory_limit', '1024M');
set_time_limit(0);

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/sync_lib.php';

runImport($pdo);
