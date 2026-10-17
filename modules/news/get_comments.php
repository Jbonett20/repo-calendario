<?php
/**
 * =====================================================
 *  monchomania - API: comentarios de una noticia (JSON)
 *  Parámetro GET: noticia_id
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/comment_interactions.php';

$noticiaId = (int) ($_GET['noticia_id'] ?? 0);
if ($noticiaId <= 0) {
    json_response(['error' => 'Identificador de noticia inválido.'], 400);
}

if (!is_admin() && !module_is_visible('noticias')) {
    json_response(['error' => 'El módulo de Noticias no está disponible.'], 403);
}

ensure_app_tables();
$comments = comment_interaction_list(
    Database::connection(),
    'noticia',
    $noticiaId,
    (int) $user['id']
);
foreach ($comments as &$comment) {
    $comment['can_manage'] = is_admin();
}
unset($comment);
json_response($comments);
