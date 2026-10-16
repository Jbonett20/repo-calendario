<?php
/**
 * =====================================================
 *  monchomania - API: quiénes dieron "me gusta" (JSON)
 *  Parámetros GET: tipo (publicacion|comentario) e id
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';

if (!is_admin() && !module_is_visible('publicaciones')) {
    json_response(['error' => 'El módulo de Publicaciones no está disponible.'], 403);
}

$tipo = (string) ($_GET['tipo'] ?? 'publicacion');
$id   = (int) ($_GET['id'] ?? 0);

if (posts_likes_source($tipo) === null || $id <= 0) {
    json_response(['error' => 'Datos incompletos.'], 422);
}

ensure_app_tables();

$estado = posts_like_state(Database::connection(), $tipo, $id, (int) $user['id']);

json_response([
    'success' => true,
    'total'   => $estado['total'],
    'html'    => posts_likers_html($estado['likers']),
]);
