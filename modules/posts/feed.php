<?php
/**
 * =====================================================
 *  monchomania - API: siguiente página del feed (JSON)
 *  Parámetro GET: offset (cuántas publicaciones ya se ven)
 *  Devuelve el HTML de la página siguiente y si quedan más.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';

if (!is_admin() && !module_is_visible('publicaciones')) {
    json_response(['error' => 'El módulo de Publicaciones no está disponible.'], 403);
}

$offset = max(0, (int) ($_GET['offset'] ?? 0));

ensure_app_tables();

$pagina = posts_render_page(
    Database::connection(),
    (int) $user['id'],
    is_admin(),
    $offset,
    posts_page_size()
);

json_response([
    'success'    => true,
    'html'       => $pagina['html'],
    'hay_mas'    => $pagina['hay_mas'],
    'mostradas'  => $pagina['mostradas'],
    'nextOffset' => $offset + $pagina['mostradas'],
]);
