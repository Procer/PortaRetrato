<?php
// Inicializa el panel (lo corre install-panel.sh):  php setup.php [clave-admin]
// Crea la base, el código de alta y la clave de administrador (si no existen).
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__ . '/lib.php';
db();
$pass = $argv[1] ?? '';
if (cfg('enroll_code') === '') setCfg('enroll_code', bin2hex(random_bytes(8)));
if ($pass !== '') setCfg('admin_hash', password_hash($pass, PASSWORD_DEFAULT));
elseif (cfg('admin_hash') === '') { fwrite(STDERR, "Falta la clave de administrador\n"); exit(1); }
echo "Código de alta (PANEL_ENROLL): " . cfg('enroll_code') . "\n";
