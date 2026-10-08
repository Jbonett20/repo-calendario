<?php
/**
 * =====================================================
 *  monchomania - API: editar un saludo de cumpleaños
 *  Puede editarlo el propio autor o un administrador.
 *  Recibe JSON: { comment_id, comentario }
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método no permitido.'], 405);
}

// Verificar CSRF (enviado por cabecera X-CSRF-Token)
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || !is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'], $token)) {
    json_response(['error' => 'Token de seguridad inválido.'], 403);
}

$payload    = json_decode(file_get_contents('php://input'), true);
$commentId  = (int) ($payload['comment_id'] ?? 0);
$comentario = limpiar_texto($payload['comentario'] ?? '', 500);

if ($commentId <= 0 || $comentario === '') {
    json_response(['error' => 'Datos incompletos.'], 422);
}

$pdo  = Database::connection();
$stmt = $pdo->prepare('SELECT id_usuario_autor FROM cumple_comentarios WHERE id = ? LIMIT 1');
$stmt->execute([$commentId]);
$autorId = $stmt->fetchColumn();

if ($autorId === false) {
    json_response(['error' => 'El saludo ya no existe.'], 404);
}

$esAutor = (int) $autorId === (int) $user['id'];
if (!$esAutor && !is_admin()) {
    json_response(['error' => 'No puedes editar este saludo.'], 403);
}

$stmt = $pdo->prepare('UPDATE cumple_comentarios SET comentario = ? WHERE id = ?');
$stmt->execute([$comentario, $commentId]);

json_response([
    'success'    => true,
    'comentario' => $comentario,
    'message'    => 'Saludo actualizado.',
]);
