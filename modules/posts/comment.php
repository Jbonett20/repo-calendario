<?php
/**
 * =====================================================
 *  monchomania - API: comentar una publicación
 *  Recibe JSON: { publicacion_id, comentario }
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';

posts_api_guard();

$payload       = posts_json_payload();
$publicacionId = (int) ($payload['publicacion_id'] ?? 0);
$comentario    = limpiar_texto($payload['comentario'] ?? '', 500);

if ($publicacionId <= 0 || $comentario === '') {
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

$pdo->prepare(
    'INSERT INTO publicaciones_comentarios (id_publicacion, id_usuario, comentario) VALUES (?, ?, ?)'
)->execute([$publicacionId, $user['id'], $comentario]);

// Avisar al autor de la publicación (si no es él mismo quien comenta)
if ((int) $autorId !== (int) $user['id']) {
    notify_user(
        (int) $autorId,
        'comentario',
        trim($user['nombre'] . ' ' . $user['apellidos']) . ' comentó tu publicación',
        base_url('modules/posts/index.php#post-' . $publicacionId)
    );
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM publicaciones_comentarios WHERE id_publicacion = ?');
$stmt->execute([$publicacionId]);

json_response(['success' => true, 'total' => (int) $stmt->fetchColumn()]);
