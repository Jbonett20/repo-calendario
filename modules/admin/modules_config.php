<?php
/**
 * =====================================================
 *  monchomania - Configuración de Módulos (solo superadmin)
 *  Define qué módulos son visibles para los usuarios normales.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_admin();

$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_request()) {
        $msg = ['error', 'Token de seguridad inválido.'];
    } else {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id FROM modulos');
        $modulos = $stmt->fetchAll();

        foreach ($modulos as $m) {
            $visible = isset($_POST['modulo_' . $m['id']]) ? 1 : 0;
            $upd = $pdo->prepare('UPDATE permisos_modulos SET visible = ? WHERE id_rol = 2 AND id_modulo = ?');
            $upd->execute([$visible, $m['id']]);
        }
        $msg = ['success', 'Configuración de módulos guardada correctamente.'];
    }
}

// Lista de módulos y su visibilidad para el rol "usuario" (2)
$stmt = Database::connection()->query(
    "SELECT m.id, m.nombre, m.clave_modulo, m.descripcion, COALESCE(pm.visible, 0) AS visible
       FROM modulos m
       LEFT JOIN permisos_modulos pm ON pm.id_modulo = m.id AND pm.id_rol = 2
      ORDER BY m.id"
);
$modulos = $stmt->fetchAll();

$pageTitle = 'Configuración de Módulos';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-4">
    <h1 class="h3 mb-2">🧩 Configuración de Módulos</h1>
    <p class="text-muted mb-4">
        Activa o desactiva los módulos que los <strong>usuarios normales</strong> pueden ver.
        El superadministrador siempre tiene acceso a todo.
    </p>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg[0] === 'success' ? 'success' : 'danger' ?>" data-auto-dismiss>
            <?= e($msg[1]) ?>
        </div>
    <?php endif; ?>

    <div class="card mm-card shadow-sm">
        <div class="card-body">
            <form method="post" action="">
                <?= csrf_field() ?>
                <?php foreach ($modulos as $m): ?>
                    <div class="d-flex align-items-center justify-content-between py-3 border-bottom">
                        <div class="me-3">
                            <div class="fw-semibold"><?= e($m['nombre']) ?></div>
                            <div class="text-muted small">
                                <code><?= e($m['clave_modulo']) ?></code> · <?= e($m['descripcion']) ?>
                            </div>
                        </div>
                        <div class="form-check form-switch ms-3">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="modulo_<?= (int) $m['id'] ?>" name="modulo_<?= (int) $m['id'] ?>"
                                   <?= (int) $m['visible'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="modulo_<?= (int) $m['id'] ?>">
                                <?= (int) $m['visible'] === 1 ? 'Visible' : 'Oculto' ?>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary mt-4 px-4">Guardar configuración</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
