<?php
/**
 * =====================================================
 *  monchomania - API: marcar las notificaciones como vistas
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

notifications_mark_all_read((int) $user['id']);

json_response(['success' => true]);
