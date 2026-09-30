<?php
/**
 * Barra de navegación principal.
 * Muestra u oculta módulos según rol y configuración.
 */
$currentUser = $currentUser ?? (is_logged_in() ? current_user() : null);
?>
<nav class="navbar navbar-expand-lg navbar-dark mm-navbar sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand mm-brand" href="<?= base_url('index.php') ?>">
            <span class="brand-mark">M</span> monchomania
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
                            </ul>
                        </li>
                    <?php endif; ?>

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
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
