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

if (!is_admin() && !module_is_visible('publicaciones')) {
    json_response(['error' => 'El módulo de Publicaciones no está disponible.'], 403);
}

$publicacionId = (int) ($_GET['publicacion_id'] ?? 0);
if ($publicacionId <= 0) {
    json_response(['error' => 'Identificador de publicación inválido.'], 400);
}

ensure_app_tables();

$pdo     = Database::connection();
$userId  = (int) $user['id'];
$esAdmin = is_admin();
$result  = [];

foreach (posts_comments($pdo, $publicacionId) as $c) {
    $commentId = (int) $c['id'];
    $likes     = posts_like_state($pdo, 'comentario', $commentId, $userId);

    $result[] = [
        'id'             => $commentId,
        'comentario'     => $c['comentario'],
        'fecha_creacion' => $c['fecha_creacion'],
        'autor'          => trim($c['nombre'] . ' ' . $c['apellidos']),
        'foto_url'       => posts_avatar_url($c['foto']),
        'likes_total'    => $likes['total'],
        'liked'          => $likes['liked'],
        'can_manage'     => posts_can_manage((int) $c['id_usuario'], $userId, $esAdmin),
    ];
}

json_response($result);
