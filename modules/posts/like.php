<?php
/**
 * =====================================================
 *  monchomania - API: dar o quitar "me gusta" a una publicación
 *  Recibe JSON: { publicacion_id }
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';

posts_api_guard();

$payload       = posts_json_payload();
$publicacionId = (int) ($payload['publicacion_id'] ?? 0);

if ($publicacionId <= 0) {
    json_response(['error' => 'Datos incompletos.'], 422);
}

ensure_app_tables();

$pdo  = Database::connection();
$stmt = $pdo->prepare('SELECT id_autor FROM publicaciones WHERE id = ? LIMIT 1');
$stmt->execute([$publicacionId]);
$autorId = $stmt->fetchColumn();

if ($autorId === false) {
    json_response(['error' => 'La publicación no existe.'], 404);
}

$userId = (int) $user['id'];
$estado = posts_like_state($pdo, 'publicacion', $publicacionId, $userId);

if ($estado['liked']) {
    $pdo->prepare('DELETE FROM publicaciones_likes WHERE id_publicacion = ? AND id_usuario = ?')
        ->execute([$publicacionId, $userId]);
} else {
    $pdo->prepare('INSERT IGNORE INTO publicaciones_likes (id_publicacion, id_usuario) VALUES (?, ?)')
        ->execute([$publicacionId, $userId]);

    // Avisar al autor (si no es él mismo quien reacciona)
    if ((int) $autorId !== $userId) {
        notify_user(
            (int) $autorId,
            'me_gusta',
            trim($user['nombre'] . ' ' . $user['apellidos']) . ' le dio me gusta a tu publicación',
            base_url('modules/posts/index.php#post-' . $publicacionId)
        );
    }
}

$estado = posts_like_state($pdo, 'publicacion', $publicacionId, $userId);

json_response([
    'success' => true,
    'liked'   => $estado['liked'],
    'total'   => $estado['total'],
    'resumen' => $estado['resumen'],
]);
