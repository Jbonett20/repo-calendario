<?php
/**
 * =====================================================
 *  monchomania - Registro de usuarios
 *  Rol por defecto: 2 (usuario) | Estado por defecto: 1 (activo)
 * =====================================================
 */
require_once __DIR__ . '/../../config/app.php';

if (is_logged_in()) {
    redirect('modules/calendar/index.php');
}

$errors = [];
$old = [
    'usuario'           => '',
    'nombre'            => '',
    'apellidos'         => '',
    'fecha_nacimiento'  => '',
    'direccion'         => '',
    'barrio'            => '',
    'zona'              => 'Urbana',
    'email'             => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'usuario'          => trim($_POST['usuario'] ?? ''),
        'nombre'           => trim($_POST['nombre'] ?? ''),
        'apellidos'        => trim($_POST['apellidos'] ?? ''),
        'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? '',
        'direccion'        => trim($_POST['direccion'] ?? ''),
        'barrio'           => trim($_POST['barrio'] ?? ''),
        'zona'             => $_POST['zona'] ?? 'Urbana',
        'email'            => trim($_POST['email'] ?? ''),
    ];

    if (!verify_csrf_request()) {
        $errors[] = 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.';
    } else {
        // Validaciones
        if ($old['nombre'] === '' || $old['apellidos'] === '') {
            $errors[] = 'El nombre y los apellidos son obligatorios.';
        }
        if ($old['usuario'] === '') {
            $errors[] = 'El nombre de usuario es obligatorio.';
        } elseif (!preg_match('/^[a-zA-Z0-9_.\-]{3,50}$/', $old['usuario'])) {
            $errors[] = 'El usuario solo puede tener letras, números, punto, guion o guion bajo (3-50 caracteres).';
        }
        if ($old['fecha_nacimiento'] === '' || !strtotime($old['fecha_nacimiento'])) {
            $errors[] = 'La fecha de nacimiento es obligatoria y debe ser válida.';
        }
        // El correo es opcional: solo se valida si se escribió
        if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo electrónico no es válido.';
        }
        if (!in_array($old['zona'], ['Urbana', 'Vereda'], true)) {
            $old['zona'] = 'Urbana';
        }

        $password = $_POST['password'] ?? '';
        if (strlen($password) < 6) {
            $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
        }

        // Usuario único
        $stmt = Database::connection()->prepare('SELECT id FROM usuarios WHERE usuario = ? LIMIT 1');
        $stmt->execute([$old['usuario']]);
        if ($stmt->fetch()) {
            $errors[] = 'Ese nombre de usuario ya está en uso. Elige otro.';
        }

        // Email único (solo si se proporcionó)
        if ($old['email'] !== '') {
            $stmt = Database::connection()->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
            $stmt->execute([$old['email']]);
            if ($stmt->fetch()) {
                $errors[] = 'Ese correo electrónico ya está registrado.';
            }
        }

        if (!$errors) {
            try {
                // Foto de perfil (opcional)
                $foto = '';
                if (!empty($_FILES['foto']['name'])) {
                    $foto = handle_photo_upload($_FILES['foto']);
                }

                $stmt = Database::connection()->prepare(
                    'INSERT INTO usuarios
                       (id_rol, usuario, nombre, apellidos, fecha_nacimiento, direccion, barrio, zona, foto, email, password, estado)
                     VALUES (2, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
                );
                $stmt->execute([
                    $old['usuario'],
                    $old['nombre'],
                    $old['apellidos'],
                    $old['fecha_nacimiento'],
                    $old['direccion'] !== '' ? $old['direccion'] : null,
                    $old['barrio'] !== '' ? $old['barrio'] : null,
                    $old['zona'],
                    $foto !== '' ? $foto : null,
                    $old['email'] !== '' ? $old['email'] : null,
                    password_hash($password, PASSWORD_DEFAULT),
                ]);

                flash('success', 'Registro exitoso. Ya puedes iniciar sesión.');
                redirect('modules/auth/login.php');
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Registrarse';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="auth-wrapper">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card mm-card shadow">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <img src="<?= asset('img/logo.png') ?>" alt="monchomania" class="mm-logo-lg">
                            <p class="text-muted mb-0 mt-3">Crea tu cuenta y únete a la comunidad</p>
                        </div>

                        <?php if ($errors): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= e($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="" enctype="multipart/form-data" novalidate>
                            <?= csrf_field() ?>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="usuario">Nombre de usuario *</label>
                                    <input type="text" id="usuario" name="usuario" class="form-control"
                                           value="<?= e($old['usuario']) ?>" placeholder="Ej.: juan123" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="email">Correo electrónico <span class="text-muted small">(opcional)</span></label>
                                    <input type="email" id="email" name="email" class="form-control"
                                           value="<?= e($old['email']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="nombre">Nombre *</label>
                                    <input type="text" id="nombre" name="nombre" class="form-control"
                                           value="<?= e($old['nombre']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="apellidos">Apellidos *</label>
                                    <input type="text" id="apellidos" name="apellidos" class="form-control"
                                           value="<?= e($old['apellidos']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="fecha_nacimiento">Fecha de nacimiento *</label>
                                    <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control"
                                           value="<?= e($old['fecha_nacimiento']) ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="direccion">Dirección (opcional)</label>
                                    <input type="text" id="direccion" name="direccion" class="form-control"
                                           value="<?= e($old['direccion']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="barrio">Barrio</label>
                                    <input type="text" id="barrio" name="barrio" class="form-control"
                                           value="<?= e($old['barrio']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="zona">Zona</label>
                                    <select id="zona" name="zona" class="form-select">
                                        <option value="Urbana" <?= $old['zona'] === 'Urbana' ? 'selected' : '' ?>>Urbana</option>
                                        <option value="Vereda" <?= $old['zona'] === 'Vereda' ? 'selected' : '' ?>>Vereda</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="password">Contraseña * (mín. 6 caracteres)</label>
                                    <div class="position-relative">
                                        <input type="password" id="password" name="password" class="form-control pe-5" required>
                                        <button type="button" class="btn password-toggle position-absolute top-50 end-0 translate-middle-y me-1"
                                                data-target="#password" aria-label="Mostrar contraseña" tabindex="-1"></button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="foto">Foto de perfil (opcional)</label>
                                    <input type="file" id="foto" name="foto" accept="image/*"
                                           class="form-control photo-input" data-preview="#registerPreview">
                                </div>
                                <div class="col-12 text-center d-none" id="registerPreviewWrap">
                                    <img id="registerPreview" src="<?= asset('img/avatar-default.svg') ?>"
                                         class="mm-avatar-lg" alt="Vista previa">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 mt-4">Crear cuenta</button>
                        </form>

                        <p class="text-center mt-3 mb-0 small">
                            ¿Ya tienes cuenta? <a href="<?= base_url('modules/auth/login.php') ?>">Inicia sesión</a>
                        </p>

                        <p class="text-center mt-2 mb-0">
                            <button type="button" class="btn btn-link btn-sm js-install-app text-decoration-none">
                                📲 Descargar como app
                            </button>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
