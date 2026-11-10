<?php
require_once '../backend/bootstrap.php';
require_once '../backend/config.php';
session_start();

if (isset($_SESSION['gestion_auth']) && $_SESSION['gestion_auth'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';

// Freno a la fuerza bruta: 5 fallos por IP cada 15 minutos.
$ip = preg_replace('/[^0-9a-f:.]/i', '', $_SERVER['REMOTE_ADDR'] ?? 'x');
$throttleFile = sys_get_temp_dir() . '/pr_login_' . md5($ip);
$fails = [];
if (is_file($throttleFile)) {
    $fails = array_filter((array)json_decode(@file_get_contents($throttleFile), true), function ($t) { return $t > time() - 900; });
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST['password'] ?? '';
    // La clave cambiada desde Gestión (en la base) manda; si no, la de config.local.php.
    $okPass = checkGestionPassword($pdo, $input);
    if (count($fails) >= 5) {
        $error = 'Demasiados intentos. Esperá 15 minutos.';
    } elseif ($okPass) {
        @unlink($throttleFile);
        session_regenerate_id(true);
        $_SESSION['gestion_auth'] = true;
        header('Location: index.php');
        exit;
    } else {
        $fails[] = time();
        @file_put_contents($throttleFile, json_encode(array_values($fails)));
        $error = 'Contraseña incorrecta';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#5b5bf0">
    <meta name="color-scheme" content="light dark">
    <title>Aura</title>
    <style>
        :root { --bg:#f4f4f7; --surface:#fff; --surface-2:#eeeef3; --text:#131318; --muted:#6c6c78; --accent:#5b5bf0; --danger:#e5484d; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0a0a0e; --surface:#15151b; --surface-2:#1d1d25; --text:#f3f3f6; --muted:#9a9aa8; --accent:#7c7cff; --danger:#ff6369; } }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px;
               background: var(--bg); color: var(--text); -webkit-font-smoothing: antialiased;
               background-image: radial-gradient(circle at 15% 10%, rgba(91,91,240,.16), transparent 45%), radial-gradient(circle at 90% 90%, rgba(139,92,246,.14), transparent 45%); }
        .card { width:100%; max-width:380px; background:var(--surface); border-radius:30px; padding:40px 30px 30px; box-shadow:0 1px 2px rgba(0,0,0,.05), 0 24px 70px rgba(30,30,80,.16); border:1px solid rgba(128,128,150,.15); }
        .logo { width:58px; height:58px; border-radius:18px; background:linear-gradient(135deg,#5b5bf0,#8b5cf6); display:flex; align-items:center; justify-content:center; margin-bottom:22px; box-shadow:0 10px 26px rgba(91,91,240,.4); }
        .logo i { width:28px; height:21px; border:3.5px solid #fff; border-radius:5px; display:block; }
        h1 { font-size:30px; font-weight:800; letter-spacing:-.03em; }
        p { color:var(--muted); margin:4px 0 26px; font-size:15px; }
        input { width:100%; min-height:52px; padding:0 18px; border-radius:16px; border:1.5px solid transparent; background:var(--surface-2); font:inherit; font-size:16px; color:inherit; outline:none; transition:border-color .2s, background .2s; }
        input:focus { border-color:var(--accent); background:var(--surface); }
        button { margin-top:14px; width:100%; min-height:52px; border:none; border-radius:999px; background:linear-gradient(135deg,#5b5bf0,#8b5cf6); color:#fff; font:inherit; font-weight:700; font-size:16px; cursor:pointer; box-shadow:0 8px 22px rgba(91,91,240,.38); transition:transform .15s, filter .2s; }
        button:hover { filter:brightness(1.07); } button:active { transform:scale(.98); }
        .pw { position:relative; }
        .pw input { padding-right:84px; }
        .pw .eye { position:absolute; right:6px; top:6px; width:auto; min-height:40px; margin:0; padding:0 16px; background:transparent; box-shadow:none; color:var(--accent); font-size:14px; }
        .pw .eye:hover { filter:none; background:rgba(128,128,150,.12); }
        .err { background:rgba(229,72,77,.12); color:var(--danger); border-radius:14px; padding:12px 16px; font-weight:600; font-size:14px; margin-bottom:14px; }
    </style>
</head>
<body>
    <form class="card" method="POST" autocomplete="off">
        <div class="logo"><i></i></div>
        <h1>Aura</h1>
        <p>Ingresá la contraseña para administrar el portarretrato.</p>
        <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="pw">
            <input type="password" name="password" id="pw" placeholder="Contraseña" autofocus
                   autocomplete="current-password" autocapitalize="none" autocorrect="off" spellcheck="false" enterkeyhint="go">
            <button type="button" class="eye" id="eye" aria-label="Mostrar contraseña">Ver</button>
        </div>
        <button type="submit">Entrar</button>
        <script>
            document.getElementById('eye').onclick = function () {
                var i = document.getElementById('pw'), show = i.type === 'password';
                i.type = show ? 'text' : 'password';
                this.textContent = show ? 'Ocultar' : 'Ver';
            };
        </script>
    </form>
</body>
</html>
