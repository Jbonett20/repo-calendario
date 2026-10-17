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
$parentId = is_array($payload) ? (int) ($payload['comment_id'] ?? 0) : 0;
$replyText = is_array($payload) && is_string($payload['comentario'] ?? null)
    ? $payload['comentario']
    : '';
$reply = limpiar_texto($replyText, 500);
$source = comment_interaction_source($tipo);
if ($source === null || $parentId <= 0 || $reply === '') {
    json_response(['error' => 'Datos incompletos.'], 422);
}
if (mb_strlen($reply) > 500) {
    json_response(['error' => 'La respuesta es demasiado larga (máx. 500 caracteres).'], 422);
}
if (!comment_interaction_access($tipo)) {
    json_response(['error' => 'Este módulo no está disponible.'], 403);
}

ensure_app_tables();
$pdo = Database::connection();
$stmt = $pdo->prepare(
    'SELECT `' . $source['author'] . '` AS autor_id, `' . $source['target'] . '` AS destino_id,
            id_comentario_padre
       FROM `' . $source['comments'] . '` WHERE id = ? LIMIT 1'
);
$stmt->execute([$parentId]);
$parent = $stmt->fetch();
if (!$parent || $parent['id_comentario_padre'] !== null) {
    json_response(['error' => 'Solo se puede responder a un comentario principal.'], 404);
}

$stmt = $pdo->prepare(
    'INSERT INTO `' . $source['comments'] . '` (`' . $source['target'] . '`, `' . $source['author']
        . '`, id_comentario_padre, comentario) VALUES (?, ?, ?, ?)'
);
$stmt->execute([(int) $parent['destino_id'], (int) $user['id'], $parentId, $reply]);

if ((int) $parent['autor_id'] !== (int) $user['id']) {
    $path = $tipo === 'noticia'
        ? 'modules/news/detail.php?id=' . (int) $parent['destino_id']
        : ($tipo === 'publicacion'
            ? 'modules/posts/index.php#post-' . (int) $parent['destino_id']
            : 'modules/profile/view.php?id=' . (int) $parent['destino_id']);
    notify_user(
        (int) $parent['autor_id'],
        'comentario',
        trim($user['nombre'] . ' ' . $user['apellidos']) . ' respondió a tu comentario',
        base_url($path)
    );
}

json_response(['success' => true]);
