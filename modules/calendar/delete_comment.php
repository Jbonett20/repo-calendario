<?php
/**
 * =====================================================
 *  monchomania - API: eliminar un saludo de cumpleaños (solo superadmin)
 *  Recibe JSON: { comment_id }
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método no permitido.'], 405);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || !is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'], $token)) {
    json_response(['error' => 'Token de seguridad inválido.'], 403);
}

$payload   = json_decode(file_get_contents('php://input'), true);
$commentId = (int) ($payload['comment_id'] ?? 0);

if ($commentId <= 0) {
    json_response(['error' => 'Datos incompletos.'], 422);
}

$stmt = Database::connection()->prepare('DELETE FROM cumple_comentarios WHERE id = ?');
$stmt->execute([$commentId]);

if ($stmt->rowCount() === 0) {
    json_response(['error' => 'El saludo ya no existe.'], 404);
}

json_response(['success' => true, 'message' => 'Saludo eliminado.']);
