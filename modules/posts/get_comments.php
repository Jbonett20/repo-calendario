<?php
/**
 * =====================================================
 *  monchomania - API: comentarios de una publicación (JSON)
 *  Parámetro GET: publicacion_id
 *  Incluye, por comentario, si el usuario actual puede
 *  borrarlo y el estado de sus "me gusta".
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../includes/comment_interactions.php';

if (!is_admin() && !module_is_visible('publicaciones')) {
    json_response(['error' => 'El módulo de Publicaciones no está disponible.'], 403);
}

$publicacionId = (int) ($_GET['publicacion_id'] ?? 0);
if ($publicacionId <= 0) {
    json_response(['error' => 'Identificador de publicación inválido.'], 400);
}

ensure_app_tables();

$userId = (int) $user['id'];
$result = comment_interaction_list(
    Database::connection(),
    'publicacion',
    $publicacionId,
    $userId
);
foreach ($result as &$comment) {
    $comment['can_manage'] = posts_can_manage((int) $comment['autor_id'], $userId, is_admin());
}
unset($comment);

json_response($result);
