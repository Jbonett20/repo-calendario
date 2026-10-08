<?php
/**
 * Barra de navegación principal.
 * Muestra u oculta módulos según rol y configuración.
 */
$currentUser = $currentUser ?? (is_logged_in() ? current_user() : null);

$notifSinVer = 0;
$notificaciones = [];
if ($currentUser) {
    $notifSinVer    = notifications_unread_count((int) $currentUser['id']);
    $notificaciones = notifications_recent((int) $currentUser['id']);
}
?>
<nav class="navbar navbar-expand-lg navbar-dark mm-navbar sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand mm-brand" href="<?= base_url('index.php') ?>">
            <img src="<?= asset('img/logo.png') ?>" alt="monchomania" class="mm-logo">
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if ($currentUser): ?>

                    <?php if (module_is_visible('calendario')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= base_url('modules/calendar/index.php') ?>">
                                🎂 Calendario
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (module_is_visible('noticias')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= base_url('modules/news/index.php') ?>">
                                📰 Noticias
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (is_admin()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">
                                ⚙️ Administración
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= base_url('modules/admin/users.php') ?>">👥 Gestión de Usuarios</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('modules/admin/modules_config.php') ?>">🧩 Configuración de Módulos</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('modules/events/index.php') ?>">📌 Eventos y Reuniones</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- Descargar como app (PWA) -->
                    <li class="nav-item">
                        <a class="nav-link js-install-app" href="#" role="button" title="Descargar como app">
                            📲 Descargar app
                        </a>
                    </li>

                    <!-- Notificaciones -->
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative mm-bell" href="#" role="button"
                           data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                           aria-label="Notificaciones"
                           data-read-url="<?= base_url('modules/notifications/read.php') ?>"
                           data-delete-url="<?= base_url('modules/notifications/delete.php') ?>">
                            🔔
                            <span class="badge rounded-pill mm-notif-badge js-notif-badge <?= $notifSinVer ? '' : 'd-none' ?>">
                                <?= (int) $notifSinVer ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end mm-notif-menu">
                            <li class="dropdown-header d-flex justify-content-between align-items-center gap-2">
                                <span>🔔 Notificaciones</span>
                                <?php if ($notificaciones): ?>
                                    <button type="button"
                                            class="btn btn-link btn-sm p-0 text-decoration-none js-notif-dismiss-all">
                                        Limpiar todas
                                    </button>
                                <?php endif; ?>
                            </li>
                            <li class="js-notif-empty <?= $notificaciones ? 'd-none' : '' ?>">
                                <span class="dropdown-item-text text-muted small">
                                    No tienes notificaciones todavía.
                                </span>
                            </li>
                            <?php foreach ($notificaciones as $nf): ?>
                                <li class="mm-notif-row">
                                    <a class="dropdown-item mm-notif-item <?= (int) $nf['leida'] === 0 ? 'mm-notif-new' : '' ?>"
                                       href="<?= $nf['url'] ? e($nf['url']) : base_url('index.php') ?>">
                                        <span class="mm-notif-icon"><?= notification_icon($nf['tipo']) ?></span>
                                        <span class="mm-notif-text"><?= e($nf['mensaje']) ?></span>
                                    </a>
                                    <button type="button" class="mm-notif-dismiss js-notif-dismiss"
                                            data-notif-id="<?= (int) $nf['id'] ?>"
                                            title="Quitar notificación" aria-label="Quitar notificación">✕</button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= e($currentUser['foto'] ? uploads_url('profiles/' . $currentUser['foto']) : asset('img/avatar-default.svg')) ?>"
                                 class="nav-avatar" alt="Foto de perfil">
                            <span><?= e($currentUser['nombre']) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= base_url('modules/profile/index.php') ?>">👤 Mi Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= base_url('modules/auth/logout.php') ?>">🚪 Cerrar Sesión</a>
                            </li>
                        </ul>
                    </li>

                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('modules/auth/login.php') ?>">Iniciar Sesión</a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-light btn-sm mm-btn-outline" href="<?= base_url('modules/auth/register.php') ?>">Registrarse</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link js-install-app" href="#" role="button" title="Descargar como app">
                            📲 Descargar app
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
