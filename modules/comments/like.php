<?php
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/comment_interactions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método no permitido.'], 405);
}
if (!comment_interaction_csrf_valid()) {
    json_response(['error' => 'Token de seguridad inválido.'], 403);
}

$payload = json_decode(file_get_contents('php://input'), true);
$tipo = is_array($payload) && is_string($payload['tipo'] ?? null)
    ? $payload['tipo']
    : '';
$commentId = is_array($payload) ? (int) ($payload['comment_id'] ?? 0) : 0;
$source = comment_interaction_source($tipo);
if ($source === null || $commentId <= 0) {
    json_response(['error' => 'Datos incompletos.'], 422);
}
if (!comment_interaction_access($tipo)) {
    json_response(['error' => 'Este módulo no está disponible.'], 403);
}

ensure_app_tables();
$pdo = Database::connection();
$stmt = $pdo->prepare(
    'SELECT `' . $source['author'] . '` AS autor_id, `' . $source['target']
        . '` AS destino_id FROM `' . $source['comments'] . '` WHERE id = ? LIMIT 1'
);
$stmt->execute([$commentId]);
$comment = $stmt->fetch();
if (!$comment) {
    json_response(['error' => 'El comentario ya no existe.'], 404);
}

$userId = (int) $user['id'];
$stmt = $pdo->prepare(
    'SELECT 1 FROM `' . $source['likes'] . '` WHERE id_comentario = ? AND id_usuario = ? LIMIT 1'
);
$stmt->execute([$commentId, $userId]);
$liked = $stmt->fetchColumn() !== false;

if ($liked) {
    $pdo->prepare('DELETE FROM `' . $source['likes'] . '` WHERE id_comentario = ? AND id_usuario = ?')
        ->execute([$commentId, $userId]);
} else {
    $pdo->prepare('INSERT IGNORE INTO `' . $source['likes'] . '` (id_comentario, id_usuario) VALUES (?, ?)')
        ->execute([$commentId, $userId]);
    if ((int) $comment['autor_id'] !== $userId) {
        $path = $tipo === 'noticia'
            ? 'modules/news/detail.php?id=' . (int) $comment['destino_id']
            : ($tipo === 'publicacion'
                ? 'modules/posts/index.php#post-' . (int) $comment['destino_id']
                : 'modules/profile/view.php?id=' . (int) $comment['destino_id']);
        notify_user(
            (int) $comment['autor_id'],
            'me_gusta',
            trim($user['nombre'] . ' ' . $user['apellidos']) . ' le dio me gusta a tu comentario',
            base_url($path)
        );
    }
}
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $source['likes'] . '` WHERE id_comentario = ?');
$countStmt->execute([$commentId]);
json_response([
    'success' => true,
    'liked' => !$liked,
    'total' => (int) $countStmt->fetchColumn(),
]);
