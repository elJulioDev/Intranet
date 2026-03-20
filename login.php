<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/csrf.php';

if (!empty($_SESSION['funcionario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $rut   = isset($_POST['rut'])   ? $_POST['rut']   : '';
    $clave = isset($_POST['clave']) ? $_POST['clave'] : '';
    list($ok, $msg) = attempt_login($pdo, $rut, $clave);
    if ($ok) {
        header('Location: dashboard.php');
        exit;
    }
    $error = $msg;
}

$year = date('Y');
?>
<!DOCTYPE html>
<html lang="es" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intranet Municipal · Iniciar sesión</title>
    <link rel="stylesheet" href="static/css/theme.css">
    <link rel="stylesheet" href="static/css/login.css">
    <link rel="icon" type="image/x-icon" href="static/img/logo.png">
</head>
<body>

<main class="login-page">
    <!-- ── Zona central ───────────────────────────────────── -->
    <div class="login-main">

        <!-- Logo real de la municipalidad -->
        <div class="login-logo">
            <img
                src="static/img/ORIGINALN.png"
                alt="Logo Municipalidad de Coltauco"
                draggable="false">
        </div>

        <!-- Título -->
        <h1 class="login-heading">Iniciar sesión</h1>

        <!-- Tarjeta del formulario -->
        <div class="login-card">

            <?php if ($error): ?>
            <div class="login-error" role="alert">
                <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 1a7 7 0 1 1 0 14A7 7 0 0 1 8 1zm0 1.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zm-.75 3.25h1.5v4h-1.5v-4zm0 5h1.5v1.5h-1.5v-1.5z"/>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">

                <!-- RUT -->
                <div class="login-field">
                    <label class="login-label" for="rut">RUT</label>
                    <input
                        class="login-input"
                        type="text"
                        id="rut"
                        name="rut"
                        placeholder="12.345.678-9"
                        autocomplete="username"
                        autocapitalize="none"
                        spellcheck="false"
                        required
                        value="<?php echo isset($_POST['rut']) ? htmlspecialchars($_POST['rut']) : ''; ?>">
                    <p class="login-input-hint">Ingresa con puntos y guión.</p>
                </div>

                <!-- Clave -->
                <div class="login-field">
                    <label class="login-label" for="clave">Clave</label>
                    <input
                        class="login-input"
                        type="password"
                        id="clave"
                        name="clave"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required>
                </div>

                <button class="login-submit" type="submit">Ingresar</button>
            </form>

        </div><!-- /.login-card -->

        <!-- Tarjeta soporte -->
        <div class="login-footer-card">
            ¿Problemas para acceder?&nbsp;
            <a href="mailto:soporte@coltauco.cl">Contactar a soporte TI</a>
        </div>

    </div><!-- /.login-main -->

    <!-- ── Footer inferior ────────────────────────────────── -->
    <footer class="login-bottom">
        <div class="login-bottom-left">
            <span>Inicio Intranet</span>
        </div>

        <div class="login-bottom-right">
            <span>Municipalidad de Coltauco</span>
            <span class="sep">·</span>
            <span>© <?php echo $year; ?></span>
            <span class="sep">·</span>
            <a href="mailto:soporte@coltauco.cl" style="color:inherit;text-decoration:none;">soporte@coltauco.cl</a>
        </div>
    </footer>

</main>
</body>
</html>