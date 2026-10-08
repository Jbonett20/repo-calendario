<?php
/**
 * =====================================================
 *  monchomania - API: dar o quitar "me gusta" a una noticia
 *  Recibe JSON: { noticia_id }
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';

if (!is_admin() && !module_is_visible('noticias')) {
    json_response(['error' => 'El módulo de Noticias no está disponible.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método no permitido.'], 405);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || !is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'], $token)) {
    json_response(['error' => 'Token de seguridad inválido.'], 403);
}

$payload   = json_decode(file_get_contents('php://input'), true);
$noticiaId = (int) ($payload['noticia_id'] ?? 0);

if ($noticiaId <= 0) {
    json_response(['error' => 'Datos incompletos.'], 422);
}

$pdo  = Database::connection();
$stmt = $pdo->prepare('SELECT id FROM noticias WHERE id = ? LIMIT 1');
$stmt->execute([$noticiaId]);
if ($stmt->fetchColumn() === false) {
    json_response(['error' => 'La publicación no existe.'], 404);
}

ensure_app_tables();

$userId = (int) $user['id'];
$liked  = news_user_liked($pdo, $noticiaId, $userId);

if ($liked) {
    $pdo->prepare('DELETE FROM noticias_likes WHERE id_noticia = ? AND id_usuario = ?')
        ->execute([$noticiaId, $userId]);
} else {
    $pdo->prepare('INSERT IGNORE INTO noticias_likes (id_noticia, id_usuario) VALUES (?, ?)')
        ->execute([$noticiaId, $userId]);
}

json_response([
    'success' => true,
    'liked'   => !$liked,
    'total'   => news_likes_count($pdo, $noticiaId),
    'resumen' => news_likes_summary($pdo, $noticiaId, $userId),
]);
