<?php
/**
 * =====================================================
 *  monchomania - Guardar una publicación (cualquier usuario)
 *  Texto obligatorio e imagen opcional.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';

if (!is_admin() && !module_is_visible('publicaciones')) {
    flash('error', 'El módulo de Publicaciones no está disponible actualmente.');
    redirect('modules/calendar/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/posts/index.php');
}

// Si el POST supera post_max_size, PHP deja $_POST y $_FILES vacíos.
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'La imagen es demasiado grande para el servidor (máximo: '
        . ini_get('post_max_size') . ').');
    redirect('modules/posts/index.php');
}

if (!verify_csrf_request()) {
    flash('error', 'Token de seguridad inválido.');
    redirect('modules/posts/index.php');
}

$contenido = limpiar_texto($_POST['contenido'] ?? '', 2000);
$errores   = [];

if ($contenido === '') {
    $errores[] = 'Escribe algo para publicar.';
}

$imagen = '';
if (!empty($_FILES['imagen']['name'])) {
    try {
        $imagen = handle_photo_upload($_FILES['imagen'], 'posts');
    } catch (RuntimeException $e) {
        $errores[] = $e->getMessage();
    }
}

if ($errores) {
    flash('error', implode(' ', $errores));
    redirect('modules/posts/index.php');
}

ensure_app_tables();

$stmt = Database::connection()->prepare(
    'INSERT INTO publicaciones (id_autor, contenido, imagen) VALUES (?, ?, ?)'
);
$stmt->execute([$user['id'], $contenido, $imagen !== '' ? $imagen : null]);

flash('success', '¡Publicación compartida! 🎉');
redirect('modules/posts/index.php');
