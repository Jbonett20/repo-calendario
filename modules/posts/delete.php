<?php
/**
 * =====================================================
 *  monchomania - Eliminar una publicación
 *  Puede el autor (la suya) o el superadmin (cualquiera).
 *  Los comentarios y "me gusta" se borran en cascada.
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

if (!verify_csrf_request()) {
    flash('error', 'Token de seguridad inválido.');
    redirect('modules/posts/index.php');
}

$publicacionId = (int) ($_POST['id'] ?? 0);
if ($publicacionId <= 0) {
    flash('error', 'Publicación no válida.');
    redirect('modules/posts/index.php');
}

ensure_app_tables();

$pdo  = Database::connection();
$stmt = $pdo->prepare('SELECT id_autor, imagen FROM publicaciones WHERE id = ? LIMIT 1');
$stmt->execute([$publicacionId]);
$publicacion = $stmt->fetch();

if (!$publicacion) {
    flash('error', 'La publicación ya no existe.');
    redirect('modules/posts/index.php');
}

if (!posts_can_manage((int) $publicacion['id_autor'], (int) $user['id'], is_admin())) {
    flash('error', 'No puedes eliminar esta publicación.');
    redirect('modules/posts/index.php');
}

$pdo->prepare('DELETE FROM publicaciones WHERE id = ?')->execute([$publicacionId]);
delete_photo($publicacion['imagen'], 'posts');

flash('success', 'Publicación eliminada.');
redirect('modules/posts/index.php');
