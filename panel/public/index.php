<?php
// Panel de administración de portarretratos.
require_once __DIR__ . '/../lib.php';
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$flash = '';

function redirect(string $to = './'): never { header('Location: ' . $to); exit; }
function authed(): bool { return !empty($_SESSION['admin']); }
function needCsrf(): void { if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Sesión vencida: recargá la página.'); } }

// ── Acciones (POST) ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['a'] ?? '';
    if ($a === 'login') {
        $f = sys_get_temp_dir() . '/pp_login_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        $fails = is_file($f) ? array_filter(json_decode(file_get_contents($f), true) ?: [], fn($t) => $t > time() - 900) : [];
        if (count($fails) >= 5) { $flash = 'Demasiados intentos. Esperá 15 minutos.'; }
        elseif (password_verify((string)($_POST['password'] ?? ''), cfg('admin_hash'))) { @unlink($f); session_regenerate_id(true); $_SESSION['admin'] = true; redirect(); }
        else { $fails[] = time(); file_put_contents($f, json_encode(array_values($fails))); $flash = 'Clave incorrecta'; }
    } elseif (authed()) {
        needCsrf();
        switch ($a) {
            case 'logout': session_destroy(); redirect();
            case 'dev_save':
                $id = $_POST['id'] ?? '';
                $feat = []; foreach (featureList() as $f) $feat[$f['key']] = !empty($_POST['feat'][$f['key']]);
                db()->prepare('UPDATE devices SET name=?, owner=?, notes=?, channel=?, features=? WHERE id=?')->execute([
                    mb_substr(trim($_POST['name'] ?? ''), 0, 60), mb_substr(trim($_POST['owner'] ?? ''), 0, 60), mb_substr(trim($_POST['notes'] ?? ''), 0, 500),
                    ($_POST['channel'] ?? '') === 'main' ? 'main' : 'stable', json_encode($feat), $id]);
                addEvent($id, 'admin', 'Configuración guardada desde el panel');
                redirect('?d=' . urlencode($id) . '&ok=1');
            case 'dev_cmd':
                $id = $_POST['id'] ?? ''; $cmd = $_POST['cmd'] ?? '';
                if (in_array($cmd, ['update', 'restart_visor', 'reboot'], true)) {
                    db()->prepare('INSERT INTO commands (device_id, cmd, created_at) VALUES (?, ?, ?)')->execute([$id, $cmd, time()]);
                    addEvent($id, 'admin', 'Comando enviado: ' . $cmd);
                }
                redirect('?d=' . urlencode($id) . '&cmd=1');
            case 'dev_delete':
                db()->prepare('DELETE FROM devices WHERE id=?')->execute([$_POST['id'] ?? '']); redirect();
            case 'all_cmd':
                if (($_POST['cmd'] ?? '') === 'update') foreach (db()->query('SELECT id FROM devices')->fetchAll() as $d)
                    db()->prepare('INSERT INTO commands (device_id, cmd, created_at) VALUES (?, ?, ?)')->execute([$d['id'], 'update', time()]);
                redirect('./?ok=1');
            case 'promote':
                $err = promote(); redirect('./?' . ($err ? 'err=' . urlencode($err) : 'ok=1'));
            case 'settings':
                $feat = []; foreach (featureList() as $f) $feat[$f['key']] = !empty($_POST['feat'][$f['key']]);
                setCfg('default_features', json_encode($feat));
                if (isset($_POST['github_token']) && $_POST['github_token'] !== '') setCfg('github_token', trim($_POST['github_token']));
                if (!empty($_POST['new_enroll'])) setCfg('enroll_code', bin2hex(random_bytes(8)));
                if (!empty($_POST['new_pass'])) {
                    if (strlen($_POST['new_pass']) < 8) redirect('?p=ajustes&err=' . urlencode('La clave debe tener al menos 8 caracteres'));
                    setCfg('admin_hash', password_hash($_POST['new_pass'], PASSWORD_DEFAULT));
                }
                redirect('?p=ajustes&ok=1');
        }
    }
}

// ── Vistas ───────────────────────────────────────────────────────────────────
$feats = featureList();
$csrf = $_SESSION['csrf'];
$self = h(strtok($_SERVER['REQUEST_URI'], '?'));
?><!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel de portarretratos</title><meta name="robots" content="noindex">
<style>
:root{--bg:#f4f4f7;--card:#fff;--text:#131318;--muted:#6c6c78;--line:#e3e3ea;--accent:#5b5bf0;--ok:#1f9d63;--warn:#d98a00;--bad:#e5484d}
@media(prefers-color-scheme:dark){:root{--bg:#0a0a0e;--card:#15151b;--text:#f3f3f6;--muted:#9a9aa8;--line:#2a2a34;--accent:#7c7cff;--ok:#4cd694;--warn:#f0b43b;--bad:#ff6369}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
a{color:var(--accent);text-decoration:none}header{display:flex;align-items:center;gap:16px;padding:14px 20px;background:var(--card);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:5}
header b{font-size:18px}header nav{display:flex;gap:14px;flex:1}header form{margin:0}
main{max-width:1000px;margin:0 auto;padding:20px}h1{font-size:24px;margin:6px 0 16px}h2{font-size:15px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:26px 0 10px}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:16px;margin-bottom:14px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:14px}
.dev{display:block;color:inherit}.dev:hover{border-color:var(--accent)}.dev h3{margin:0 0 4px;font-size:17px}
.dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px;background:var(--bad)}.dot.on{background:var(--ok)}
.muted{color:var(--muted);font-size:13px}.tag{display:inline-block;padding:2px 9px;border-radius:99px;font-size:12px;font-weight:600;background:color-mix(in srgb,var(--accent) 15%,transparent);color:var(--accent);margin:2px 4px 2px 0}
.tag.warn{background:color-mix(in srgb,var(--warn) 18%,transparent);color:var(--warn)}.tag.bad{background:color-mix(in srgb,var(--bad) 18%,transparent);color:var(--bad)}.tag.ok{background:color-mix(in srgb,var(--ok) 18%,transparent);color:var(--ok)}
input[type=text],input[type=password],textarea,select{width:100%;padding:10px 12px;border:1.5px solid var(--line);border-radius:10px;background:var(--bg);color:inherit;font:inherit}
label.f{display:block;font-weight:600;margin:12px 0 4px}.chk{display:flex;gap:10px;align-items:flex-start;padding:9px 0;border-top:1px solid var(--line)}.chk:first-child{border-top:0}.chk input{margin-top:5px;width:18px;height:18px}
button,.btn{display:inline-block;padding:9px 16px;border:0;border-radius:10px;background:var(--accent);color:#fff;font:inherit;font-weight:600;cursor:pointer}
button.sec{background:var(--line);color:var(--text)}button.bad{background:var(--bad)}
.flash{padding:10px 14px;border-radius:10px;margin-bottom:14px;background:color-mix(in srgb,var(--ok) 18%,transparent);color:var(--ok);font-weight:600}.flash.err{background:color-mix(in srgb,var(--bad) 18%,transparent);color:var(--bad)}
table{width:100%;border-collapse:collapse}td,th{padding:7px 6px;border-top:1px solid var(--line);text-align:left;font-size:14px;vertical-align:top}th{color:var(--muted);font-weight:600;border-top:0}
code{background:var(--bg);padding:2px 6px;border-radius:6px;font-size:13px}.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.login{max-width:340px;margin:12vh auto}
</style></head><body>
<?php if (!authed()): ?>
<main class="login"><div class="card"><h1>Panel de portarretratos</h1>
<?php if ($flash) echo '<div class="flash err">' . h($flash) . '</div>'; ?>
<form method="post"><input type="hidden" name="a" value="login"><label class="f">Clave de administrador</label><input type="password" name="password" autofocus autocomplete="current-password"><p><button style="width:100%">Entrar</button></p></form></div></main>
</body></html><?php exit; endif; ?>

<header><b>🖼 Portarretratos</b><nav><a href="<?= $self ?>">Equipos</a><a href="?p=ajustes">Ajustes</a></nav>
<form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="a" value="logout"><button class="sec">Salir</button></form></header>
<main>
<?php
if (isset($_GET['ok'])) echo '<div class="flash">Listo</div>';
if (isset($_GET['err'])) echo '<div class="flash err">' . h($_GET['err']) . '</div>';

function devStatus(array $d): array { return json_decode($d['status'] ?: '{}', true) ?: []; }
function isOnline(array $d): bool { return $d['last_seen'] && time() - (int)$d['last_seen'] < ONLINE_MIN * 60; }
$heads = ['stable' => branchHead('stable'), 'main' => branchHead('main')];

// ── Detalle de un equipo ─────────────────────────────────────────────────────
if (!empty($_GET['d'])) {
    $q = db()->prepare('SELECT * FROM devices WHERE id=?'); $q->execute([$_GET['d']]); $d = $q->fetch();
    if (!$d) { echo '<p>Equipo no encontrado. <a href="' . $self . '">Volver</a></p></main></body></html>'; exit; }
    $s = devStatus($d); $on = isOnline($d); $df = deviceFeatures($d);
    $target = $heads[$d['channel']] ?? '';
    $upToDate = $target !== '' && ($s['version'] ?? '') === $target;
    ?>
    <p><a href="<?= $self ?>">← Todos los equipos</a></p>
    <h1><span class="dot <?= $on ? 'on' : '' ?>"></span><?= h($d['name'] ?: $d['host'] ?: 'Equipo sin nombre') ?></h1>
    <div class="card">
        <div class="row"><span class="tag <?= $on ? 'ok' : 'bad' ?>"><?= $on ? 'En línea' : 'Sin conexión' ?></span> <span class="muted">último aviso <?= ago((int)$d['last_seen']) ?></span></div>
        <table style="margin-top:10px">
            <tr><td class="muted">Versión instalada</td><td><code><?= h($s['version'] ?? '?') ?></code> (<?= h($s['branch'] ?? '') ?>) <?= $target ? ($upToDate ? '<span class="tag ok">al día</span>' : '<span class="tag warn">hay versión nueva ' . h($target) . '</span>') : '' ?></td></tr>
            <tr><td class="muted">Último update</td><td><?php $u = $s['ultimo_update'] ?? null; echo $u ? h(($u['ts'] ?? '') . ' — ' . ($u['msg'] ?? '')) . (empty($u['ok']) ? ' <span class="tag bad">falló</span>' : '') : '—'; ?></td></tr>
            <tr><td class="muted">WiFi / red</td><td><?= h(($s['wifi'] ?? '') ?: (($s['modo_red'] ?? '') ?: '—')) ?> · IP local <?= h(implode(', ', array_slice(array_filter($s['ips'] ?? [], fn($i) => strpos($i, ':') === false), 0, 2))) ?> · pública <?= h($s['ip_publica'] ?? '') ?></td></tr>
            <tr><td class="muted">Contenido</td><td><?= (int)($s['fotos'] ?? 0) ?> fotos · <?= (int)($s['videos'] ?? 0) ?> videos · ciudad: <?= h($s['ciudad'] ?? '—') ?></td></tr>
            <tr><td class="muted">Sistema</td><td>Disco <?= (int)($s['disco_pct'] ?? 0) ?>% · encendido hace <?= round(($s['uptime_s'] ?? 0) / 3600, 1) ?> h · Visor <?= !empty($s['visor_ok']) ? '<span class="tag ok">responde</span>' : '<span class="tag bad">no responde</span>' ?> · asistente <?= !empty($s['setup_ok']) ? 'completado' : '<span class="tag warn">sin completar</span>' ?></td></tr>
        </table>
    </div>

    <form method="post" class="card"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="a" value="dev_save"><input type="hidden" name="id" value="<?= h($d['id']) ?>">
        <label class="f">Nombre del equipo</label><input type="text" name="name" value="<?= h($d['name']) ?>" maxlength="60" placeholder="Ej: Casa de Marcos">
        <label class="f">Dueño / contacto</label><input type="text" name="owner" value="<?= h($d['owner']) ?>" maxlength="60">
        <label class="f">Notas</label><textarea name="notes" rows="2" maxlength="500"><?= h($d['notes']) ?></textarea>
        <label class="f">Canal de actualización</label>
        <select name="channel"><option value="stable" <?= $d['channel'] === 'stable' ? 'selected' : '' ?>>stable — solo lo que vos promoviste (clientes)</option><option value="main" <?= $d['channel'] === 'main' ? 'selected' : '' ?>>main — lo último que programás (tu equipo de pruebas)</option></select>
        <h2>Funciones de este equipo</h2>
        <?php foreach ($feats as $f): ?><label class="chk"><input type="checkbox" name="feat[<?= h($f['key']) ?>]" value="1" <?= !empty($df[$f['key']]) ? 'checked' : '' ?>><span><b><?= h($f['label']) ?></b><br><span class="muted"><?= h($f['desc']) ?></span></span></label><?php endforeach; ?>
        <p class="muted">Los cambios llegan al equipo en unos 10 minutos.</p>
        <button>Guardar</button>
    </form>

    <div class="card"><h2 style="margin-top:0">Acciones</h2><div class="row">
        <?php foreach (['update' => 'Actualizar ahora', 'restart_visor' => 'Reabrir el Visor', 'reboot' => 'Reiniciar equipo'] as $c => $l): ?>
        <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="a" value="dev_cmd"><input type="hidden" name="id" value="<?= h($d['id']) ?>"><input type="hidden" name="cmd" value="<?= $c ?>"><button class="sec"><?= $l ?></button></form>
        <?php endforeach; ?></div>
        <?php $cm = db()->prepare('SELECT * FROM commands WHERE device_id=? ORDER BY id DESC LIMIT 6'); $cm->execute([$d['id']]); $cm = $cm->fetchAll();
        if ($cm): ?><table style="margin-top:12px"><tr><th>Comando</th><th>Enviado</th><th>Resultado</th></tr><?php foreach ($cm as $c): ?><tr><td><?= h($c['cmd']) ?></td><td><?= $c['sent_at'] ? ago((int)$c['sent_at']) : 'esperando al equipo' ?></td><td><?= $c['ok'] === null ? '—' : ($c['ok'] ? '✔ ' : '✖ ') . h($c['msg']) ?></td></tr><?php endforeach; ?></table><?php endif; ?>
    </div>

    <div class="card"><h2 style="margin-top:0">Historial</h2>
        <?php $ev = db()->prepare('SELECT * FROM events WHERE device_id=? ORDER BY id DESC LIMIT 25'); $ev->execute([$d['id']]); $ev = $ev->fetchAll();
        if (!$ev) echo '<p class="muted">Sin eventos.</p>'; else { echo '<table>'; foreach ($ev as $e) echo '<tr><td class="muted" style="white-space:nowrap">' . h(date('d/m H:i', (int)$e['ts'])) . '</td><td>' . ($e['kind'] === 'error' ? '<span class="tag bad">error</span>' : '') . h($e['msg']) . '</td></tr>'; echo '</table>'; } ?>
    </div>

    <form method="post" onsubmit="return confirm('¿Eliminar este equipo del panel? Deja de recibir funciones y actualizaciones desde acá.')"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="a" value="dev_delete"><input type="hidden" name="id" value="<?= h($d['id']) ?>"><button class="bad">Eliminar equipo</button></form>
    </main></body></html><?php exit;
}

// ── Ajustes ──────────────────────────────────────────────────────────────────
if (($_GET['p'] ?? '') === 'ajustes') { $dfe = defaultFeatures(); ?>
    <h1>Ajustes</h1>
    <form method="post" class="card"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="a" value="settings">
        <h2 style="margin-top:0">Código de alta de equipos nuevos</h2>
        <p>Va en el pendrive de instalación (<code>PANEL_ENROLL</code>). Quien no lo tenga no puede registrar equipos.</p>
        <p><code style="font-size:16px"><?= h(cfg('enroll_code')) ?></code></p>
        <label class="chk"><input type="checkbox" name="new_enroll" value="1"><span>Generar un código nuevo (hay que volver a preparar el pendrive; los equipos ya registrados siguen andando)</span></label>

        <h2>Funciones por defecto para equipos nuevos</h2>
        <?php foreach ($feats as $f): ?><label class="chk"><input type="checkbox" name="feat[<?= h($f['key']) ?>]" value="1" <?= !empty($dfe[$f['key']]) ? 'checked' : '' ?>><span><b><?= h($f['label']) ?></b></span></label><?php endforeach; ?>

        <h2>GitHub (botón «Promover a stable»)</h2>
        <p class="muted">Token de GitHub con permiso <b>Contents: Read and write</b> sobre <?= h(REPO) ?>. Opcional: sin token promovés con <code>git push origin main:stable</code>.</p>
        <input type="password" name="github_token" placeholder="<?= cfg('github_token') ? 'Hay un token guardado (dejá vacío para conservarlo)' : 'github_pat_…' ?>" autocomplete="off">

        <h2>Clave de administrador</h2>
        <input type="password" name="new_pass" placeholder="Nueva clave (mínimo 8, vacío para no cambiar)" autocomplete="new-password">
        <p><button>Guardar ajustes</button></p>
    </form></main></body></html><?php exit; }

// ── Lista de equipos ─────────────────────────────────────────────────────────
$devs = db()->query('SELECT * FROM devices ORDER BY last_seen DESC')->fetchAll();
$mainSha = $heads['main']; $stableSha = $heads['stable'];
?>
<h1>Equipos</h1>
<div class="card row">
    <span>Versiones: <b>main</b> <code><?= h($mainSha ?: '?') ?></code> · <b>stable</b> <code><?= h($stableSha ?: 'sin crear') ?></code></span>
    <?php if ($mainSha && $mainSha !== $stableSha): ?><span class="tag warn">hay cambios sin promover</span><?php endif; ?>
    <span style="flex:1"></span>
    <form method="post" onsubmit="return confirm('¿Pasar lo último de main a stable? Esa noche se instala en todos los equipos de clientes.')"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="a" value="promote"><button>Promover main → stable</button></form>
    <form method="post" onsubmit="return confirm('¿Actualizar todos los equipos ahora?')"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="a" value="all_cmd"><input type="hidden" name="cmd" value="update"><button class="sec">Actualizar todos ahora</button></form>
</div>
<?php if (!$devs): ?><div class="card"><p>Todavía no hay equipos. Se registran solos al instalarse con el pendrive (o corriendo <code>instalar-agente.sh</code> en un equipo ya instalado).</p></div><?php endif; ?>
<div class="grid">
<?php foreach ($devs as $d): $s = devStatus($d); $on = isOnline($d); $target = $heads[$d['channel']] ?? ''; $ok = $target !== '' && ($s['version'] ?? '') === $target; $u = $s['ultimo_update'] ?? null;
    $off = array_keys(array_filter(deviceFeatures($d), fn($v) => !$v)); ?>
    <a class="card dev" href="?d=<?= h($d['id']) ?>">
        <h3><span class="dot <?= $on ? 'on' : '' ?>"></span><?= h($d['name'] ?: $d['host'] ?: 'Sin nombre') ?></h3>
        <div class="muted"><?= h($d['owner']) ?><?= $d['owner'] ? ' · ' : '' ?><?= $on ? 'en línea' : 'visto ' . ago((int)$d['last_seen']) ?></div>
        <div style="margin-top:8px">
            <span class="tag"><?= h($d['channel']) ?></span>
            <span class="tag <?= $ok ? 'ok' : 'warn' ?>"><?= h($s['version'] ?? '?') ?><?= $ok ? '' : ' · desactualizado' ?></span>
            <?php if ($u && empty($u['ok'])): ?><span class="tag bad">update falló</span><?php endif; ?>
            <?php if (isset($s['visor_ok']) && !$s['visor_ok']): ?><span class="tag bad">Visor caído</span><?php endif; ?>
            <?php if (isset($s['setup_ok']) && !$s['setup_ok']): ?><span class="tag warn">sin configurar</span><?php endif; ?>
            <?php if (($s['disco_pct'] ?? 0) > 85): ?><span class="tag bad">disco <?= (int)$s['disco_pct'] ?>%</span><?php endif; ?>
            <?php if ($off): ?><span class="tag">sin: <?= h(implode(', ', $off)) ?></span><?php endif; ?>
        </div>
    </a>
<?php endforeach; ?>
</div>
</main></body></html>
