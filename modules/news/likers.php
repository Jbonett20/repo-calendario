<?php
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';

if (!is_admin() && !module_is_visible('noticias')) {
    json_response(['error' => 'El módulo de Noticias no está disponible.'], 403);
}

$noticiaId = (int) ($_GET['id'] ?? 0);
if ($noticiaId <= 0) {
    json_response(['error' => 'Identificador de noticia inválido.'], 422);
}

$stmt = Database::connection()->prepare('SELECT 1 FROM noticias WHERE id = ? LIMIT 1');
$stmt->execute([$noticiaId]);
if ($stmt->fetchColumn() === false) {
    json_response(['error' => 'La publicación no existe.'], 404);
}

ensure_app_tables();
$likers = news_likers(Database::connection(), $noticiaId);
json_response(['success' => true, 'html' => news_likers_html($likers)]);
