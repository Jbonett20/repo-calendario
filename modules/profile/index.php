<?php
/**
 * =====================================================
 *  monchomania - Módulo Perfil
 *  Ver y actualizar datos personales y foto.
 *  El usuario NO puede cambiar su estado ni su rol.
 *  Al cambiar la foto, se elimina la anterior (unlink).
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';

$errors  = [];
$success = false;
$user    = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_request()) {
        $errors[] = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $usuario          = trim($_POST['usuario'] ?? '');
        $nombre           = trim($_POST['nombre'] ?? '');
        $apellidos        = trim($_POST['apellidos'] ?? '');
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
        $direccion        = trim($_POST['direccion'] ?? '');
        $barrio           = trim($_POST['barrio'] ?? '');
        $zona             = $_POST['zona'] ?? 'Urbana';

        if ($usuario === '') {
            $errors[] = 'El nombre de usuario es obligatorio.';
        } elseif (!preg_match('/^[a-zA-Z0-9_.\-]{3,50}$/', $usuario)) {
            $errors[] = 'El usuario solo puede tener letras, números, punto, guion o guion bajo (3-50 caracteres).';
        }
        if ($nombre === '' || $apellidos === '') {
            $errors[] = 'El nombre y los apellidos son obligatorios.';
        }
        if ($fecha_nacimiento === '' || !strtotime($fecha_nacimiento)) {
            $errors[] = 'La fecha de nacimiento debe ser válida.';
        }
        if (!in_array($zona, ['Urbana', 'Vereda'], true)) {
            $zona = 'Urbana';
        }

        // Nueva contraseña (opcional)
        $nueva_password = $_POST['password'] ?? '';
        $password_hash  = null;
        if ($nueva_password !== '') {
            if (strlen($nueva_password) < 6) {
                $errors[] = 'La nueva contraseña debe tener al menos 6 caracteres.';
            } else {
                $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
            }
        }

        // Unicidad del usuario (excluyendo al propio usuario)
        $stmt = Database::connection()->prepare('SELECT id FROM usuarios WHERE usuario = ? AND id <> ? LIMIT 1');
        $stmt->execute([$usuario, $user['id']]);
        if ($stmt->fetch()) {
            $errors[] = 'Ese nombre de usuario ya está en uso.';
        }

        if (!$errors) {
            try {
                $foto = $user['foto'];

                // Si sube una foto nueva: guardar la nueva y borrar la anterior
                if (!empty($_FILES['foto']['name'])) {
                    $nueva_foto = handle_photo_upload($_FILES['foto']);
                    if ($nueva_foto !== '' && $foto !== null && $foto !== '') {
                        delete_photo($foto); // eliminar imagen anterior del servidor
                    }
                    $foto = $nueva_foto !== '' ? $nueva_foto : $foto;
                }

                $pdo = Database::connection();
                if ($password_hash !== null) {
                    $stmt = $pdo->prepare(
                        'UPDATE usuarios
                            SET usuario = ?, nombre = ?, apellidos = ?, fecha_nacimiento = ?, direccion = ?,
                                barrio = ?, zona = ?, foto = ?, password = ?
                          WHERE id = ?'
                    );
                    $stmt->execute([
                        $usuario, $nombre, $apellidos, $fecha_nacimiento,
                        $direccion !== '' ? $direccion : null,
                        $barrio !== '' ? $barrio : null,
                        $zona, $foto, $password_hash, $user['id'],
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE usuarios
                            SET usuario = ?, nombre = ?, apellidos = ?, fecha_nacimiento = ?, direccion = ?,
                                barrio = ?, zona = ?, foto = ?
                          WHERE id = ?'
                    );
                    $stmt->execute([
                        $usuario, $nombre, $apellidos, $fecha_nacimiento,
                        $direccion !== '' ? $direccion : null,
                        $barrio !== '' ? $barrio : null,
                        $zona, $foto, $user['id'],
                    ]);
                }

                $success = true;
                $user    = current_user(); // recargar datos actualizados
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

// Calcular edad
$edad = null;
if ($user['fecha_nacimiento']) {
    $nacimiento = new DateTime($user['fecha_nacimiento']);
    $hoy        = new DateTime('today');
    $edad       = $nacimiento->diff($hoy)->y;
}

$fotoUrl = $user['foto']
    ? uploads_url('profiles/' . $user['foto'])
    : asset('img/avatar-default.svg');

$pageTitle = 'Mi Perfil';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-4">
    <h1 class="h3 mb-4">👤 Mi Perfil</h1>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success" data-auto-dismiss>Datos actualizados correctamente.</div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Columna: foto e información -->
        <div class="col-lg-4">
            <div class="card mm-card shadow-sm text-center p-4">
                <img src="<?= e($fotoUrl) ?>" alt="Foto de perfil" class="mm-avatar-lg mx-auto mb-3">
                <h2 class="h5 mb-1"><?= e($user['nombre'] . ' ' . $user['apellidos']) ?></h2>
                <p class="text-muted mb-1">@<?= e($user['usuario']) ?></p>
                <?php if ($user['email']): ?>
                    <p class="text-muted mb-3"><?= e($user['email']) ?></p>
                <?php else: ?>
                    <p class="text-muted mb-3"><em>Sin correo registrado</em></p>
                <?php endif; ?>

                <span class="badge mm-zone mb-3"><?= e($user['zona']) ?></span>
                <span class="badge mm-badge-role"><?= e($user['nombre_rol']) ?></span>

                <ul class="list-unstyled text-start small text-muted mb-0">
                    <li class="mb-1">🎂 <?= e(date('d/m/Y', strtotime($user['fecha_nacimiento']))) ?>
                        <?php if ($edad !== null): ?>(<?= $edad ?> años)<?php endif; ?></li>
                    <?php if ($user['direccion']): ?><li class="mb-1">📍 <?= e($user['direccion']) ?></li><?php endif; ?>
                    <?php if ($user['barrio']): ?><li class="mb-1">🏘️ <?= e($user['barrio']) ?></li><?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Columna: formulario de edición -->
        <div class="col-lg-8">
            <div class="card mm-card shadow-sm">
                <div class="card-header mm-card-header">Editar información</div>
                <div class="card-body">
                    <form method="post" action="" enctype="multipart/form-data" novalidate>
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="usuario">Nombre de usuario</label>
                                <input type="text" id="usuario" name="usuario" class="form-control"
                                       value="<?= e($user['usuario']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="nombre">Nombre</label>
                                <input type="text" id="nombre" name="nombre" class="form-control"
                                       value="<?= e($user['nombre']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="apellidos">Apellidos</label>
                                <input type="text" id="apellidos" name="apellidos" class="form-control"
                                       value="<?= e($user['apellidos']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label>
                                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control"
                                       value="<?= e($user['fecha_nacimiento']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="direccion">Dirección</label>
                                <input type="text" id="direccion" name="direccion" class="form-control"
                                       value="<?= e($user['direccion'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="barrio">Barrio</label>
                                <input type="text" id="barrio" name="barrio" class="form-control"
                                       value="<?= e($user['barrio'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="zona">Zona</label>
                                <select id="zona" name="zona" class="form-select">
                                    <option value="Urbana" <?= $user['zona'] === 'Urbana' ? 'selected' : '' ?>>Urbana</option>
                                    <option value="Vereda" <?= $user['zona'] === 'Vereda' ? 'selected' : '' ?>>Vereda</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="foto">Nueva foto de perfil</label>
                                <input type="file" id="foto" name="foto" accept="image/*"
                                       class="form-control photo-input" data-preview="#profilePreview">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password">Nueva contraseña <span class="text-muted small">(opcional)</span></label>
                                <div class="position-relative">
                                    <input type="password" id="password" name="password" class="form-control pe-5"
                                           placeholder="Dejar vacío para no cambiarla">
                                    <button type="button" class="btn password-toggle position-absolute top-50 end-0 translate-middle-y me-1"
                                            data-target="#password" aria-label="Mostrar contraseña" tabindex="-1"></button>
                                </div>
                            </div>
                            <div class="col-12 text-center d-none" id="profilePreviewWrap">
                                <img id="profilePreview" src="<?= e($fotoUrl) ?>" class="mm-avatar-lg" alt="Vista previa">
                            </div>
                        </div>

                        <p class="text-muted small mt-3 mb-3">
                            <em>El rol y el estado de tu cuenta solo los puede modificar un administrador.</em>
                        </p>
                        <button type="submit" class="btn btn-primary px-4">Guardar cambios</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
