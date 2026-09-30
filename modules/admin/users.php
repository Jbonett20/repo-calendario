<?php
/**
 * =====================================================
 *  monchomania - Gestión de Usuarios (solo superadmin)
 *  Activar/Inactivar usuarios y editar todos sus datos.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_admin();

$errors = [];
$msg    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_request()) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $action = $_POST['action'] ?? '';

        /* ----- Activar / Inactivar ----- */
        if ($action === 'toggle') {
            $id     = (int) ($_POST['id'] ?? 0);
            $estado = (int) ($_POST['estado'] ?? 0);

            if ($id > 0 && in_array($estado, [1, 2], true)) {
                if ($id === (int) $user['id'] && $estado === 2) {
                    $errors[] = 'No puedes inactivarte a ti mismo.';
                } else {
                    $stmt = Database::connection()->prepare('UPDATE usuarios SET estado = ? WHERE id = ?');
                    $stmt->execute([$estado, $id]);
                    $msg = $estado === 1 ? 'Usuario activado correctamente.' : 'Usuario inactivado correctamente.';
                }
            }
        }

        /* ----- Editar usuario ----- */
        if ($action === 'edit') {
            $id               = (int) ($_POST['id'] ?? 0);
            $usuario          = trim($_POST['usuario'] ?? '');
            $nombre           = trim($_POST['nombre'] ?? '');
            $apellidos        = trim($_POST['apellidos'] ?? '');
            $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
            $direccion        = trim($_POST['direccion'] ?? '');
            $barrio           = trim($_POST['barrio'] ?? '');
            $zona             = $_POST['zona'] ?? 'Urbana';
            $email            = trim($_POST['email'] ?? '');
            $id_rol           = (int) ($_POST['id_rol'] ?? 2);
            $estado           = (int) ($_POST['estado'] ?? 1);

            if ($usuario === '') {
                $errors[] = 'El nombre de usuario es obligatorio.';
            } elseif (!preg_match('/^[a-zA-Z0-9_.\-]{3,50}$/', $usuario)) {
                $errors[] = 'El usuario solo puede tener letras, números, punto, guion o guion bajo (3-50 caracteres).';
            }
            if ($nombre === '' || $apellidos === '') {
                $errors[] = 'El nombre y los apellidos son obligatorios.';
            }
            // El correo es opcional
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'El correo electrónico no es válido.';
            }
            if ($fecha_nacimiento === '' || !strtotime($fecha_nacimiento)) {
                $errors[] = 'La fecha de nacimiento debe ser válida.';
            }
            if (!in_array($zona, ['Urbana', 'Vereda'], true)) {
                $zona = 'Urbana';
            }
            if (!in_array($id_rol, [1, 2], true)) {
                $id_rol = 2;
            }
            if (!in_array($estado, [1, 2], true)) {
                $estado = 1;
            }

            // Unicidad del usuario (excluyendo al propio usuario)
            $stmt = Database::connection()->prepare('SELECT id FROM usuarios WHERE usuario = ? AND id <> ? LIMIT 1');
            $stmt->execute([$usuario, $id]);
            if ($stmt->fetch()) {
                $errors[] = 'Ese nombre de usuario ya pertenece a otro usuario.';
            }

            // Unicidad del email (solo si se proporcionó, excluyendo al propio usuario)
            if ($email !== '') {
                $stmt = Database::connection()->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ? LIMIT 1');
                $stmt->execute([$email, $id]);
                if ($stmt->fetch()) {
                    $errors[] = 'Ese correo electrónico ya pertenece a otro usuario.';
                }
            }

            if (!$errors) {
                try {
                    // Foto actual del usuario
                    $stmt = Database::connection()->prepare('SELECT foto FROM usuarios WHERE id = ?');
                    $stmt->execute([$id]);
                    $fotoActual = $stmt->fetchColumn() ?: null;

                    $foto = $fotoActual;
                    if (!empty($_FILES['foto']['name'])) {
                        $nueva = handle_photo_upload($_FILES['foto']);
                        if ($nueva !== '' && $fotoActual !== null && $fotoActual !== '') {
                            delete_photo($fotoActual); // eliminar foto anterior
                        }
                        $foto = $nueva !== '' ? $nueva : $foto;
                    }

                    // Contraseña opcional
                    $password_hash = null;
                    $nueva_password = $_POST['password'] ?? '';
                    if ($nueva_password !== '') {
                        if (strlen($nueva_password) < 6) {
                            $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
                        } else {
                            $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
                        }
                    }

                    if (!$errors) {
                        $pdo = Database::connection();
                        $emailVal = $email !== '' ? $email : null;

                        if ($password_hash !== null) {
                            $stmt = $pdo->prepare(
                                'UPDATE usuarios
                                    SET id_rol = ?, usuario = ?, nombre = ?, apellidos = ?, fecha_nacimiento = ?, direccion = ?,
                                        barrio = ?, zona = ?, foto = ?, email = ?, password = ?, estado = ?
                                  WHERE id = ?'
                            );
                            $stmt->execute([
                                $id_rol, $usuario, $nombre, $apellidos, $fecha_nacimiento,
                                $direccion !== '' ? $direccion : null,
                                $barrio !== '' ? $barrio : null,
                                $zona, $foto, $emailVal, $password_hash, $estado, $id,
                            ]);
                        } else {
                            $stmt = $pdo->prepare(
                                'UPDATE usuarios
                                    SET id_rol = ?, usuario = ?, nombre = ?, apellidos = ?, fecha_nacimiento = ?, direccion = ?,
                                        barrio = ?, zona = ?, foto = ?, email = ?, estado = ?
                                  WHERE id = ?'
                            );
                            $stmt->execute([
                                $id_rol, $usuario, $nombre, $apellidos, $fecha_nacimiento,
                                $direccion !== '' ? $direccion : null,
                                $barrio !== '' ? $barrio : null,
                                $zona, $foto, $emailVal, $estado, $id,
                            ]);
                        }
                        $msg = 'Usuario actualizado correctamente.';
                    }
                } catch (RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}

// Lista de usuarios
$stmt = Database::connection()->query(
    "SELECT u.*, r.nombre_rol
       FROM usuarios u
       JOIN roles r ON r.id = u.id_rol
      ORDER BY u.creado_en DESC"
);
$usuarios = $stmt->fetchAll();

$activos   = array_values(array_filter($usuarios, fn($u) => (int) $u['estado'] === 1));
$inactivos = array_values(array_filter($usuarios, fn($u) => (int) $u['estado'] === 2));

/**
 * Renderiza una tabla de usuarios.
 */
function render_users_table(array $list, int $currentUserId): void
{
    foreach ($list as $u) {
        $fotoUrl = $u['foto']
            ? uploads_url('profiles/' . $u['foto'])
            : asset('img/avatar-default.svg');

        $json = json_encode([
            'id'               => (int) $u['id'],
            'usuario'          => $u['usuario'],
            'nombre'           => $u['nombre'],
            'apellidos'        => $u['apellidos'],
            'fecha_nacimiento' => $u['fecha_nacimiento'],
            'direccion'        => $u['direccion'],
            'barrio'           => $u['barrio'],
            'zona'             => $u['zona'],
            'email'            => $u['email'],
            'id_rol'           => (int) $u['id_rol'],
            'estado'           => (int) $u['estado'],
        ]);

        $esActivo  = (int) $u['estado'] === 1;
        $badge     = $esActivo
            ? '<span class="badge mm-badge-active">Activo</span>'
            : '<span class="badge mm-badge-inactive">Inactivo</span>';

        $toggleBtn = '';
        if ((int) $u['id'] !== $currentUserId) {
            $toggleBtn = $esActivo
                ? '<button class="btn btn-sm btn-outline-danger" onclick="toggleUser(' . (int) $u['id'] . ', 2)">Inactivar</button>'
                : '<button class="btn btn-sm btn-outline-success" onclick="toggleUser(' . (int) $u['id'] . ', 1)">Activar</button>';
        } else {
            $toggleBtn = '<span class="text-muted small">(tú)</span>';
        }

        $emailInfo = $u['email'] !== null && $u['email'] !== ''
            ? e($u['email'])
            : '<em>sin correo</em>';

        echo '<tr>';
        echo '<td><img src="' . e($fotoUrl) . '" alt="Foto"></td>';
        echo '<td><strong>' . e($u['nombre'] . ' ' . $u['apellidos']) . '</strong><br><span class="text-muted small">@' . e($u['usuario']) . ' · ' . $emailInfo . '</span></td>';
        echo '<td>' . e($u['barrio'] !== '' && $u['barrio'] !== null ? $u['barrio'] : '—') . '</td>';
        echo '<td>' . e($u['zona']) . '</td>';
        echo '<td>' . e(date('d/m/Y', strtotime($u['fecha_nacimiento']))) . '</td>';
        echo '<td>' . e($u['nombre_rol']) . '</td>';
        echo '<td>' . $badge . '</td>';
        echo '<td class="text-nowrap">';
        echo '<button class="btn btn-sm btn-outline-primary me-1" data-user=\'' . e($json) . '\' onclick="openEditUser(this)">Editar</button>';
        echo $toggleBtn;
        echo '</td>';
        echo '</tr>';
    }
}

$pageTitle = 'Gestión de Usuarios';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-4">
    <h1 class="h3 mb-4">👥 Gestión de Usuarios</h1>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($msg): ?>
        <div class="alert alert-success" data-auto-dismiss><?= e($msg) ?></div>
    <?php endif; ?>

    <ul class="nav nav-tabs mm-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="activos-tab" data-bs-toggle="tab" data-bs-target="#activos"
                    type="button" role="tab">Activos
                <span class="badge mm-badge-active ms-1"><?= count($activos) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="inactivos-tab" data-bs-toggle="tab" data-bs-target="#inactivos"
                    type="button" role="tab">Inactivos
                <span class="badge mm-badge-inactive ms-1"><?= count($inactivos) ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content mt-3">
        <div class="tab-pane fade show active" id="activos" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-hover align-middle mm-table">
                    <thead class="table-light">
                        <tr>
                            <th>Foto</th><th>Usuario</th><th>Barrio</th><th>Zona</th>
                            <th>Nacimiento</th><th>Rol</th><th>Estado</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$activos): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No hay usuarios activos.</td></tr>
                        <?php else: ?>
                            <?php render_users_table($activos, (int) $user['id']); ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade" id="inactivos" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-hover align-middle mm-table">
                    <thead class="table-light">
                        <tr>
                            <th>Foto</th><th>Usuario</th><th>Barrio</th><th>Zona</th>
                            <th>Nacimiento</th><th>Rol</th><th>Estado</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$inactivos): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No hay usuarios inactivos.</td></tr>
                        <?php else: ?>
                            <?php render_users_table($inactivos, (int) $user['id']); ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============ Modal: editar usuario ============ -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editUserId">

                <div class="modal-header mm-modal-header">
                    <h5 class="modal-title">✏️ Editar Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre de usuario</label>
                            <input type="text" name="usuario" id="editUsuario" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-muted small">(opcional)</span></label>
                            <input type="email" name="email" id="editEmail" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" id="editNombre" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apellidos</label>
                            <input type="text" name="apellidos" id="editApellidos" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de nacimiento</label>
                            <input type="date" name="fecha_nacimiento" id="editFecha" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="direccion" id="editDireccion" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Barrio</label>
                            <input type="text" name="barrio" id="editBarrio" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Zona</label>
                            <select name="zona" id="editZona" class="form-select">
                                <option value="Urbana">Urbana</option>
                                <option value="Vereda">Vereda</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rol</label>
                            <select name="id_rol" id="editRol" class="form-select">
                                <option value="1">Superadministrador</option>
                                <option value="2">Usuario</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <select name="estado" id="editEstado" class="form-select">
                                <option value="1">Activo</option>
                                <option value="2">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nueva contraseña <span class="text-muted small">(opcional)</span></label>
                            <div class="position-relative">
                                <input type="password" id="editPassword" name="password" class="form-control pe-5" placeholder="Dejar vacío para no cambiar">
                                <button type="button" class="btn password-toggle position-absolute top-50 end-0 translate-middle-y me-1"
                                        data-target="#editPassword" aria-label="Mostrar contraseña" tabindex="-1"></button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Foto de perfil <span class="text-muted small">(opcional)</span></label>
                            <input type="file" name="foto" accept="image/*" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.MM_ADMIN_CSRF = '<?= e(csrf_token()) ?>';
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
