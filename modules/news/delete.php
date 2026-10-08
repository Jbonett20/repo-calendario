<?php
/**
 * =====================================================
 *  monchomania - Eliminar una publicación (solo superadmin)
 *  Borra también sus comentarios, sus "me gusta" y el
 *  archivo subido (imagen, video o audio).
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';

require_admin();
ensure_app_tables();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/news/index.php');
}
if (!verify_csrf_request()) {
    flash('error', 'Token de seguridad inválido.');
    redirect('modules/news/index.php');
}

$noticiaId = (int) ($_POST['id'] ?? 0);
if ($noticiaId <= 0) {
    flash('error', 'Publicación no válida.');
    redirect('modules/news/index.php');
}

$pdo  = Database::connection();
$stmt = $pdo->prepare('SELECT url_media FROM noticias WHERE id = ? LIMIT 1');
$stmt->execute([$noticiaId]);
$noticia = $stmt->fetch();

if (!$noticia) {
    flash('error', 'La publicación no existe.');
    redirect('modules/news/index.php');
}

// Las claves foráneas ON DELETE CASCADE borran comentarios y "me gusta".
$pdo->prepare('DELETE FROM noticias WHERE id = ?')->execute([$noticiaId]);

// Borra el archivo subido si el medio era local (no un enlace externo).
if (!news_media_is_link($noticia['url_media'])) {
    delete_photo($noticia['url_media'], 'news');
}

// Limpia las notificaciones que apuntaban a esta publicación.
$pdo->prepare('DELETE FROM notificaciones WHERE url = ?')
    ->execute([base_url('modules/news/detail.php?id=' . $noticiaId)]);

flash('success', 'Publicación eliminada con sus comentarios y "me gusta".');
redirect('modules/news/index.php');
