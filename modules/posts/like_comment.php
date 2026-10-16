<?php
/**
 * =====================================================
 *  monchomania - API: dar o quitar "me gusta" a un comentario
 *  Recibe JSON: { comment_id }
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';

posts_api_guard();

$payload   = posts_json_payload();
$commentId = (int) ($payload['comment_id'] ?? 0);

if ($commentId <= 0) {
    json_response(['error' => 'Datos incompletos.'], 422);
}

ensure_app_tables();

$pdo  = Database::connection();
$stmt = $pdo->prepare('SELECT id_usuario FROM publicaciones_comentarios WHERE id = ? LIMIT 1');
$stmt->execute([$commentId]);
$autorId = $stmt->fetchColumn();

if ($autorId === false) {
    json_response(['error' => 'El comentario ya no existe.'], 404);
}

$userId = (int) $user['id'];
$estado = posts_like_state($pdo, 'comentario', $commentId, $userId);

if ($estado['liked']) {
    $pdo->prepare('DELETE FROM publicaciones_comentarios_likes WHERE id_comentario = ? AND id_usuario = ?')
        ->execute([$commentId, $userId]);
} else {
    $pdo->prepare('INSERT IGNORE INTO publicaciones_comentarios_likes (id_comentario, id_usuario) VALUES (?, ?)')
        ->execute([$commentId, $userId]);

    if ((int) $autorId !== $userId) {
        notify_user(
            (int) $autorId,
            'me_gusta',
            trim($user['nombre'] . ' ' . $user['apellidos']) . ' le dio me gusta a tu comentario',
            base_url('modules/posts/index.php')
        );
    }
}

$estado = posts_like_state($pdo, 'comentario', $commentId, $userId);

json_response([
    'success' => true,
    'liked'   => $estado['liked'],
    'total'   => $estado['total'],
]);
