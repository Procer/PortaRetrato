<?php
// Aviso periódico de cada portarretrato. Autenticado con X-Device + X-Token.
require_once __DIR__ . '/../../lib.php';

$id = $_SERVER['HTTP_X_DEVICE'] ?? ''; $tok = $_SERVER['HTTP_X_TOKEN'] ?? '';
if (!preg_match('/^[a-f0-9]{24}$/', $id) || $tok === '') jsonOut(['error' => 'no autorizado'], 401);
$s = db()->prepare('SELECT * FROM devices WHERE id=?'); $s->execute([$id]); $dev = $s->fetch();
if (!$dev || !hash_equals($dev['token_hash'], hash('sha256', $tok))) jsonOut(['error' => 'no autorizado'], 401);

$st = json_decode(file_get_contents('php://input'), true);
if (!is_array($st)) jsonOut(['error' => 'datos inválidos'], 400);
$st['ip_publica'] = $_SERVER['REMOTE_ADDR'] ?? '';

// Resultados de comandos anteriores
foreach (($st['resultados'] ?? []) as $r) {
    db()->prepare('UPDATE commands SET ok=?, msg=? WHERE id=? AND device_id=?')->execute([!empty($r['ok']) ? 1 : 0, mb_substr((string)($r['msg'] ?? ''), 0, 200), (int)($r['id'] ?? 0), $id]);
}
unset($st['resultados']);

// Eventos útiles para el historial
$prev = json_decode($dev['status'] ?: '{}', true) ?: [];
if (($prev['version'] ?? '') !== ($st['version'] ?? '') && !empty($prev['version'])) addEvent($id, 'version', 'Versión ' . $prev['version'] . ' → ' . $st['version']);
$u = $st['ultimo_update'] ?? null;
if ($u && ($u['ts'] ?? '') !== ($prev['ultimo_update']['ts'] ?? '') && empty($u['ok'])) addEvent($id, 'error', 'Falló la actualización: ' . ($u['msg'] ?? ''));
if (isset($st['visor_ok']) && !$st['visor_ok'] && ($prev['visor_ok'] ?? true)) addEvent($id, 'error', 'El Visor no responde');
if (!empty($prev) && empty($prev['visor_ok']) && !empty($st['visor_ok'])) addEvent($id, 'info', 'El Visor volvió a responder');
if (empty($dev['last_seen']) || time() - (int)$dev['last_seen'] > ONLINE_MIN * 60) addEvent($id, 'info', 'En línea');

db()->prepare('UPDATE devices SET last_seen=?, status=?, host=? WHERE id=?')->execute([time(), json_encode($st), (string)($st['host'] ?? ''), $id]);

// Comandos pendientes
$cmds = db()->prepare('SELECT id, cmd FROM commands WHERE device_id=? AND sent_at IS NULL ORDER BY id'); $cmds->execute([$id]); $cmds = $cmds->fetchAll();
if ($cmds) db()->prepare('UPDATE commands SET sent_at=? WHERE device_id=? AND sent_at IS NULL')->execute([time(), $id]);

jsonOut(['features' => deviceFeatures($dev), 'channel' => $dev['channel'], 'name' => $dev['name'], 'commands' => $cmds]);
