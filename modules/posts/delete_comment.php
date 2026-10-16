<?php
/**
 * =====================================================
 *  monchomania - API: eliminar un comentario de una publicación
 *  Recibe JSON: { comment_id }
 *  Puede el autor del comentario o el superadmin.
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
$stmt = $pdo->prepare('SELECT id_usuario, id_publicacion FROM publicaciones_comentarios WHERE id = ? LIMIT 1');
$stmt->execute([$commentId]);
$comentario = $stmt->fetch();

if (!$comentario) {
    json_response(['error' => 'El comentario ya no existe.'], 404);
}

if (!posts_can_manage((int) $comentario['id_usuario'], (int) $user['id'], is_admin())) {
    json_response(['error' => 'No puedes eliminar este comentario.'], 403);
}

$pdo->prepare('DELETE FROM publicaciones_comentarios WHERE id = ?')->execute([$commentId]);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM publicaciones_comentarios WHERE id_publicacion = ?');
$stmt->execute([(int) $comentario['id_publicacion']]);

json_response(['success' => true, 'total' => (int) $stmt->fetchColumn()]);
