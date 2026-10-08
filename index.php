<?php
/**
 * =====================================================
 *  monchomania - Página de inicio / landing
 *  Los usuarios logueados van directo al calendario.
 * =====================================================
 */
require_once __DIR__ . '/config/app.php';

if (is_logged_in()) {
    redirect('modules/calendar/index.php');
}

$pageTitle = 'Inicio';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Hero -->
<section class="mm-hero text-center text-white">
    <div class="container py-5">
        <div class="py-md-5">
            <h1 class="mb-4">
                <img src="<?= asset('img/logo.png') ?>" alt="monchomania" class="mm-hero-logo">
            </h1>
           <p class="lead mt-3 mb-4">Espacio informativo y de convivencia para estar al día de todo lo nuestro.</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="<?= base_url('modules/auth/login.php') ?>" class="btn btn-light btn-lg px-4">Iniciar Sesión</a>
                <a href="<?= base_url('modules/auth/register.php') ?>" class="btn btn-outline-light btn-lg px-4">Registrarme</a>
                <button type="button" class="btn btn-outline-light btn-lg px-4 js-install-app">
                    📲 Descargar como app
                </button>
            </div>
            <p class="small mt-3 mb-0 opacity-75">
                Instálala en tu celular y ábrela como una app, sin el navegador.
            </p>
        </div>
    </div>
</section>

<!-- Características -->
<section class="container py-5">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card mm-card h-100 text-center p-4">
                <div class="mm-feature-icon">🎂</div>
                <h3 class="h5 mt-3">Calendario de Cumpleaños y eventos</h3>
                <p class="text-muted mb-0">Descubre quién está de cumpleaños y envíale un saludo especial.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mm-card h-100 text-center p-4">
                <div class="mm-feature-icon">📰</div>
                <h3 class="h5 mt-3">Noticias de la Comunidad</h3>
                <p class="text-muted mb-0">Imágenes, frases, videos y canciones para mantenernos conectados.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mm-card h-100 text-center p-4">
                <div class="mm-feature-icon">💬</div>
                <h3 class="h5 mt-3">Saludos y Comentarios</h3>
                <p class="text-muted mb-0">Comparte un mensaje con quien está celebrando su día.</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
