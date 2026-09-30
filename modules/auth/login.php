<?php
/**
 * =====================================================
 *  monchomania - Inicio de sesión
 * =====================================================
 */
require_once __DIR__ . '/../../config/app.php';

// Si ya está logueado, ir al calendario
if (is_logged_in()) {
    redirect('modules/calendar/index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_request()) {
        $error = 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.';
    } else {
        $login    = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($login === '' || $password === '') {
            $error = 'Por favor completa todos los campos.';
        } else {
            // Iniciar sesión con nombre de usuario (o correo, si lo registró)
            $stmt = Database::connection()->prepare(
                'SELECT * FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1'
            );
            $stmt->execute([$login, $login]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($password, $usuario['password'])) {
                if ((int) $usuario['estado'] !== 1) {
                    $error = 'Tu cuenta está inactiva. Contacta al administrador.';
                } else {
                    // Regenerar el ID de sesión por seguridad
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $usuario['id'];
                    flash('success', '¡Bienvenido/a, ' . $usuario['nombre'] . '!');
                    redirect('modules/calendar/index.php');
                }
            } else {
                $error = 'Usuario o contraseña incorrectos.';
            }
        }
    }
}

$pageTitle = 'Iniciar Sesión';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="auth-wrapper">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card mm-card shadow">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="mm-brand-lg">monchomania</div>
                            <p class="text-muted mb-0">Inicia sesión para continuar</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= e($error) ?></div>
                        <?php endif; ?>
                        <?php if (flash_has('error')): ?>
                            <div class="alert alert-danger"><?= e(flash('error')) ?></div>
                        <?php endif; ?>
                        <?php if (flash_has('success')): ?>
                            <div class="alert alert-success" data-auto-dismiss><?= e(flash('success')) ?></div>
                        <?php endif; ?>

                        <form method="post" action="" novalidate>
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label" for="usuario">Usuario</label>
                                <input type="text" id="usuario" name="usuario" class="form-control"
                                       placeholder="Tu nombre de usuario" required autofocus>
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="password">Contraseña</label>
                                <div class="position-relative">
                                    <input type="password" id="password" name="password" class="form-control pe-5"
                                           placeholder="••••••••" required>
                                    <button type="button" class="btn password-toggle position-absolute top-50 end-0 translate-middle-y me-1"
                                            data-target="#password" aria-label="Mostrar contraseña" tabindex="-1"></button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2">Entrar</button>
                        </form>

                        <p class="text-center mt-3 mb-0 small">
                            ¿No tienes cuenta? <a href="<?= base_url('modules/auth/register.php') ?>">Regístrate</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
