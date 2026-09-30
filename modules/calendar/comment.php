<?php
/**
 * =====================================================
 *  monchomania - API: enviar saludo de cumpleaños
 *  Recibe JSON: { usuario_id, comentario }
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
$destino    = (int) ($payload['usuario_id'] ?? 0);
$comentario = trim((string) ($payload['comentario'] ?? ''));

if ($destino <= 0 || $comentario === '') {
    json_response(['error' => 'Datos incompletos.'], 422);
}
if (mb_strlen($comentario) > 500) {
    json_response(['error' => 'El comentario es demasiado largo (máx. 500 caracteres).'], 422);
}

$stmt = Database::connection()->prepare(
    'INSERT INTO cumple_comentarios (id_usuario_destino, id_usuario_autor, comentario)
     VALUES (?, ?, ?)'
);
$stmt->execute([$destino, $user['id'], $comentario]);

json_response(['success' => true, 'message' => 'Saludo enviado.']);
