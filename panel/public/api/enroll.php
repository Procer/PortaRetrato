<?php
// Alta de un portarretrato nuevo. Exige el código de alta (Ajustes del panel) que viaja en el
// pendrive de instalación. Devuelve el token que el equipo usará en cada aviso.
require_once __DIR__ . '/../../lib.php';

$d = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (string)($d['device_id'] ?? '');
if (!preg_match('/^[a-f0-9]{24}$/', $id)) jsonOut(['error' => 'datos inválidos'], 400);
$code = cfg('enroll_code');
if ($code === '' || !hash_equals($code, (string)($d['enroll'] ?? ''))) { usleep(800000); jsonOut(['error' => 'código incorrecto'], 403); }

$exists = db()->prepare('SELECT 1 FROM devices WHERE id=?'); $exists->execute([$id]);
if ($exists->fetchColumn()) jsonOut(['error' => 'ya registrado'], 409);

$token = bin2hex(random_bytes(24));
$channel = in_array($d['channel'] ?? '', ['stable', 'main'], true) ? $d['channel'] : 'stable';
db()->prepare('INSERT INTO devices (id, token_hash, name, channel, created_at, host) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$id, hash('sha256', $token), mb_substr((string)($d['host'] ?? ''), 0, 60), $channel, time(), mb_substr((string)($d['host'] ?? ''), 0, 60)]);
addEvent($id, 'info', 'Equipo registrado');
jsonOut(['token' => $token]);
