<?php
session_start();

// Si ya está autenticado, redirigir al dashboard
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

require_once 'credentials.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validar credenciales (permitir contraseña vacía)
    if ($username === DB_USER && $password === DB_PASS) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['username'] = $username;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cashflow Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Segoe UI', sans-serif;
    }

    .login-card {
        background: white;
        border-radius: 1.5rem;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        max-width: 400px;
        width: 100%;
    }

    .login-header {
        background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
        color: white;
        padding: 2rem;
        text-align: center;
    }

    .login-body {
        padding: 2.5rem;
    }

    .form-control:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
    }

    .btn-login {
        background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
        border: none;
        padding: 0.75rem;
        font-weight: 600;
        transition: transform 0.2s;
    }

    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
    }

    .alert {
        border-radius: 0.75rem;
    }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="login-header">
            <h2 class="mb-1">💰 Cashflow Pro</h2>
            <p class="mb-0 opacity-75">Gestión Financiera</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label fw-bold">Usuario</label>
                    <input type="text" class="form-control form-control-lg" id="username" name="username"
                        placeholder="Ingresa tu usuario" required autofocus>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label fw-bold">Contraseña</label>
                    <input type="password" class="form-control form-control-lg" id="password" name="password"
                        placeholder="Ingresa tu contraseña (opcional)">
                </div>
                <button type="submit" class="btn btn-primary btn-login w-100 btn-lg">
                    Iniciar Sesión
                </button>
            </form>

            <div class="text-center mt-4">
                <small class="text-muted">© 2026 Cashflow Pro v2</small>
            </div>
        </div>
    </div>
</body>

</html>