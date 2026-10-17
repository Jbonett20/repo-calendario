<?php
/**
 * =====================================================
 *  monchomania - API: comentarios de cumpleaños (JSON)
 *  Parámetro GET: usuario_id (persona que cumple años)
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../includes/comment_interactions.php';

$destino = (int) ($_GET['usuario_id'] ?? 0);
if ($destino <= 0) {
    json_response(['error' => 'Identificador de usuario inválido.'], 400);
}

ensure_app_tables();
json_response(comment_interaction_list(
    Database::connection(),
    'saludo',
    $destino,
    (int) $user['id']
));
