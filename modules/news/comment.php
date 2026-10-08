<?php
/**
 * =====================================================
 *  monchomania - API: comentar una noticia
 *  Recibe JSON: { noticia_id, comentario }
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

$payload    = json_decode(file_get_contents('php://input'), true);
$noticiaId  = (int) ($payload['noticia_id'] ?? 0);
$comentario = limpiar_texto($payload['comentario'] ?? '', 500);

if ($noticiaId <= 0 || $comentario === '') {
    json_response(['error' => 'Datos incompletos.'], 422);
}
if (mb_strlen($comentario) > 500) {
    json_response(['error' => 'El comentario es demasiado largo (máx. 500 caracteres).'], 422);
}

$pdo  = Database::connection();
$stmt = $pdo->prepare('SELECT id_autor, titulo FROM noticias WHERE id = ? LIMIT 1');
$stmt->execute([$noticiaId]);
$noticia = $stmt->fetch();

if (!$noticia) {
    json_response(['error' => 'La publicación no existe.'], 404);
}

$stmt = $pdo->prepare(
    'INSERT INTO noticias_comentarios (id_noticia, id_usuario, comentario)
     VALUES (?, ?, ?)'
);
$stmt->execute([$noticiaId, $user['id'], $comentario]);

// Avisar al autor de la publicación (si no es él mismo quien comenta)
if ((int) $noticia['id_autor'] !== (int) $user['id']) {
    notify_user(
        (int) $noticia['id_autor'],
        'comentario',
        trim($user['nombre'] . ' ' . $user['apellidos']) . ' comentó en "' . $noticia['titulo'] . '"',
        base_url('modules/news/detail.php?id=' . $noticiaId)
    );
}

json_response(['success' => true, 'message' => 'Comentario enviado.']);
