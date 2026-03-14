<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php'); exit;
}

// Regenerar ID de sesión para evitar session fixation
session_regenerate_id(true);

$error = '';
$ip    = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Token CSRF
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Solicitud inválida. Recarga la página.';
    } else {

        // Verificar intentos fallidos
        verificarIntentos($ip);

        $usuario  = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';

        // Delay artificial para dificultar timing attacks
        usleep(random_int(100000, 300000));

        if ($usuario && $password) {
            $stmt = db()->prepare("SELECT * FROM administradores WHERE usuario = ? AND activo = 1 LIMIT 1");
            $stmt->execute([$usuario]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                // Login exitoso
                limpiarIntentos($ip);
                session_regenerate_id(true);
                $_SESSION['admin_id']                 = $admin['id'];
                $_SESSION['admin']                    = $admin;
                $_SESSION['admin']['restaurante_id']  = $admin['restaurante_id'];
                $_SESSION['login_time']               = time();
                $_SESSION['login_ip']                 = $ip;
                header('Location: dashboard.php');
                exit;
            }

            // Login fallido
            registrarIntentoFallido($ip);
            // Mismo mensaje siempre — no revelar si existe el usuario
            $error = 'Usuario o contraseña incorrectos';
        } else {
            $error = 'Completa todos los campos';
        }
    }
}

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso — RestaurantOS</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;700&display=swap');
    :root {
      --gold: #e8c07d; --gold-dark: #c9993a;
      --bg: #0f0f0f; --bg2: #1a1a1a; --bg3: #242424;
      --border: #2e2e2e; --text: #f0ece4; --text-muted: #888;
      --red: #e05c5c; --radius: 14px;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg); color: var(--text);
      min-height: 100vh; display: flex;
      align-items: center; justify-content: center;
      padding: 1rem;
      -webkit-font-smoothing: antialiased;
    }
    h1, h2 { font-family: 'Playfair Display', serif; }
    .login-box {
      background: var(--bg2); border: 1px solid var(--border);
      border-radius: 20px; padding: 2.5rem 2rem;
      width: 100%; max-width: 400px;
      box-shadow: 0 40px 80px rgba(0,0,0,.6);
    }
    .login-logo { text-align: center; margin-bottom: 2rem; }
    .login-logo h1 { color: var(--gold); font-size: 1.8rem; }
    .login-logo p  { color: var(--text-muted); font-size: .82rem; margin-top: .3rem; }
    .form-group { margin-bottom: 1.2rem; }
    .form-group label {
      display: block; color: var(--text-muted); font-size: .75rem;
      letter-spacing: .06em; text-transform: uppercase; margin-bottom: .45rem;
    }
    .form-group input {
      width: 100%; padding: .85rem 1rem; background: var(--bg);
      border: 1px solid var(--border); border-radius: 10px;
      color: var(--text); font-size: 1rem; outline: none;
      font-family: 'DM Sans', sans-serif; transition: border-color .2s;
    }
    .form-group input:focus { border-color: var(--gold); }
    .btn-login {
      width: 100%; padding: 1rem;
      background: linear-gradient(135deg, var(--gold), var(--gold-dark));
      color: #111; font-weight: 700; font-size: 1rem;
      border: none; border-radius: 10px; cursor: pointer;
      font-family: 'DM Sans', sans-serif; transition: opacity .2s;
      letter-spacing: .03em;
    }
    .btn-login:hover { opacity: .9; }
    .btn-login:disabled { opacity: .5; cursor: not-allowed; }
    .error-msg {
      background: rgba(224,92,92,.1); border: 1px solid rgba(224,92,92,.3);
      color: #f08080; padding: .75rem 1rem; border-radius: 8px;
      margin-bottom: 1.2rem; font-size: .88rem;
    }
    .aviso {
      font-size: .72rem; color: var(--text-muted);
      margin-top: .5rem; line-height: 1.5;
    }
    /* Responsive */
    @media (max-width: 400px) {
      .login-box { padding: 2rem 1.2rem; border-radius: 14px; }
      .login-logo h1 { font-size: 1.5rem; }
    }
  </style>
</head>
<body>
<div class="login-box">
  <div class="login-logo">
    <h1>MexxicanMx</h1>
    <p>Panel de administración</p>
  </div>

  <?php if ($error): ?>
    <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" onsubmit="this.querySelector('.btn-login').disabled=true">
    <!-- Token CSRF oculto -->
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="form-group">
      <label>Usuario</label>
      <input type="text" name="usuario" placeholder="usuario"
             autocomplete="username" required maxlength="60"
             value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label>Contraseña</label>
      <input type="password" name="password" placeholder="••••••••"
             autocomplete="current-password" required maxlength="100">
      <p class="aviso">⚠️ Los accesos no autorizados son monitoreados y reportados. Cualquier sospecha de no autorizacion sera investigado</p>
    </div>

    <button type="submit" class="btn-login">Entrar →</button>
  </form>
</div>
</body>
</html>