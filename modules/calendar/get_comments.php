<?php
/**
 * =====================================================
 *  monchomania - API: comentarios de cumpleaños (JSON)
 *  Parámetro GET: usuario_id (persona que cumple años)
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';

$destino = (int) ($_GET['usuario_id'] ?? 0);
if ($destino <= 0) {
    json_response(['error' => 'Identificador de usuario inválido.'], 400);
}

// Cada saludo incluye 'autor_id' para que el front sepa quién puede editarlo.
json_response(cumple_comments($destino));
