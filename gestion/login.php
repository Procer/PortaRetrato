<?php
require_once '../backend/config.php';
session_start();

if (isset($_SESSION['gestion_auth']) && $_SESSION['gestion_auth'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST['password'] ?? '';
    if (hash_equals(GESTION_PASSWORD, $input)) {
        session_regenerate_id(true);
        $_SESSION['gestion_auth'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Contraseña incorrecta';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aura — Acceso</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✨</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f7ff;
            background-image: radial-gradient(circle at 20% 20%, rgba(139,92,246,0.08) 0%, transparent 50%),
                              radial-gradient(circle at 80% 80%, rgba(45,212,191,0.08) 0%, transparent 50%);
        }
        .login-card {
            background: white;
            border-radius: 28px;
            padding: 40px 36px;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 20px 60px rgba(139,92,246,0.1), 0 4px 16px rgba(0,0,0,0.04);
        }
        .login-logo {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #8b5cf6, #2dd4bf);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 6px;
        }
        .login-sub {
            font-size: 0.8rem;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 32px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .input-wrap {
            position: relative;
            margin-bottom: 16px;
        }
        .input-wrap i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.9rem;
        }
        .input-wrap input {
            width: 100%;
            padding: 14px 14px 14px 44px;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            background: #f8fafc;
            color: #0f172a;
            transition: border-color 0.2s;
            outline: none;
        }
        .input-wrap input:focus { border-color: #8b5cf6; background: white; }
        .error-msg {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.8rem;
            color: #ef4444;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #8b5cf6, #2dd4bf);
            color: white;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 800;
            cursor: pointer;
            letter-spacing: 0.5px;
            transition: opacity 0.2s, transform 0.2s;
        }
        .btn-login:hover { opacity: 0.92; transform: scale(1.01); }
        .btn-login:active { transform: scale(0.99); }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">Aura.</div>
        <div class="login-sub">Gestión Visual</div>

        <?php if ($error): ?>
            <div class="error-msg"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="input-wrap">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Contraseña" autofocus>
            </div>
            <button type="submit" class="btn-login">ENTRAR</button>
        </form>
    </div>
</body>
</html>
