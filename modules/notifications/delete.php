<?php
/**
 * =====================================================
 *  monchomania - API: quitar notificaciones
 *  Recibe JSON: { id } para una sola, { all: true } para todas.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método no permitido.'], 405);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || !is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'], $token)) {
    json_response(['error' => 'Token de seguridad inválido.'], 403);
}

$payload = json_decode(file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$pdo     = Database::connection();
$userId  = (int) $user['id'];

if (!empty($payload['all'])) {
    $pdo->prepare('DELETE FROM notificaciones WHERE id_usuario = ?')->execute([$userId]);
} else {
    $notifId = (int) ($payload['id'] ?? 0);
    if ($notifId <= 0) {
        json_response(['error' => 'Datos incompletos.'], 422);
    }
    $pdo->prepare('DELETE FROM notificaciones WHERE id = ? AND id_usuario = ?')
        ->execute([$notifId, $userId]);
}

json_response([
    'success'  => true,
    'sinLeer'  => notifications_unread_count($userId),
]);
