<?php
/**
 * =====================================================
 *  monchomania - Usuarios de la comunidad
 *  Lista de los usuarios activos (con buscador) y acceso
 *  directo al perfil de cada uno.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';

$stmt = Database::connection()->query(
    'SELECT u.id, u.usuario, u.nombre, u.apellidos, u.foto, u.zona, u.fecha_nacimiento,
            r.nombre_rol
       FROM usuarios u
       JOIN roles r ON r.id = u.id_rol
      WHERE u.estado = 1
      ORDER BY u.nombre, u.apellidos'
);
$usuarios = $stmt->fetchAll();
$total    = count($usuarios);
$userId   = (int) $user['id'];

$pageTitle = 'Usuarios';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-4">
    <?= back_button('modules/calendar/index.php') ?>
    <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap mb-4">
        <div>
            <h1 class="h3 mb-0">👥 Usuarios de la comunidad</h1>
            <p class="text-muted mb-0">
                <?= $total ?> <?= $total === 1 ? 'miembro activo' : 'miembros activos' ?>
            </p>
        </div>

        <div class="mm-users-search">
            <label class="visually-hidden" for="usersSearch">Buscar usuario</label>
            <input type="search" id="usersSearch" class="form-control js-users-search"
                   placeholder="🔍 Buscar por nombre o usuario…" autocomplete="off">
        </div>
    </div>

    <?php if (!$usuarios): ?>
        <div class="card mm-card">
            <div class="card-body text-center text-muted py-5">
                Todavía no hay usuarios activos. 👥
            </div>
        </div>
    <?php endif; ?>

    <?php if (flash_has('error')): ?>
        <div class="alert alert-danger"><?= e(flash('error')) ?></div>
    <?php endif; ?>
    <?php if (flash_has('success')): ?>
        <div class="alert alert-success" data-auto-dismiss><?= e(flash('success')) ?></div>
    <?php endif; ?>

    <div class="mm-user-list" id="usersList">
        <?php foreach ($usuarios as $u): ?>
            <?php
            $esUno       = (int) $u['id'] === $userId;
            $esModerador = $u['nombre_rol'] !== 'usuario';
            $buscar      = mb_strtolower($u['nombre'] . ' ' . $u['apellidos'] . ' ' . $u['usuario'], 'UTF-8');
            ?>
            <a class="mm-user-row js-user-row"
               href="<?= base_url('modules/profile/view.php?id=' . (int) $u['id']) ?>"
               data-buscar="<?= e($buscar) ?>">
                <img class="mm-user-row-avatar js-profile-photo" alt=""
                     src="<?= e($u['foto'] ? uploads_url('profiles/' . $u['foto']) : asset('img/avatar-default.svg')) ?>">

                <div class="mm-user-row-info">
                    <div class="mm-user-row-name">
                        <?= e($u['nombre'] . ' ' . $u['apellidos']) ?>
                        <?php if ($esUno): ?><span class="text-muted small">(tú)</span><?php endif; ?>
                        <?php if ($esModerador): ?>
                            <span class="badge mm-badge-role"><?= e($u['nombre_rol']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mm-user-row-meta">
                        <span class="mm-user-username">@<?= e($u['usuario']) ?></span>
                        <span>🎂 <?= e(date('d/m/Y', strtotime($u['fecha_nacimiento']))) ?></span>
                        <span class="badge mm-zone"><?= e($u['zona']) ?></span>
                    </div>
                </div>

                <span class="mm-user-row-action">Ver perfil &rarr;</span>
            </a>
        <?php endforeach; ?>
    </div>

    <p class="text-muted text-center py-4 d-none" id="usersNoResults">
        Nadie coincide con tu búsqueda. 🔍
    </p>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
