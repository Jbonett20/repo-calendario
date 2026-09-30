<?php
/**
 * =====================================================
 *  monchomania - API: comentarios de cumpleaños (JSON)
 *  Parámetro GET: usuario_id (persona que cumple años)
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';

$destino = (int) ($_GET['usuario_id'] ?? 0);
if ($destino <= 0) {
    json_response(['error' => 'Identificador de usuario inválido.'], 400);
}

$stmt = Database::connection()->prepare(
    "SELECT c.id, c.comentario, c.fecha_creacion,
            u.nombre, u.apellidos, u.foto
       FROM cumple_comentarios c
       JOIN usuarios u ON u.id = c.id_usuario_autor
      WHERE c.id_usuario_destino = ?
      ORDER BY c.fecha_creacion DESC"
);
$stmt->execute([$destino]);
$comentarios = $stmt->fetchAll();

$result = [];
foreach ($comentarios as $c) {
    $result[] = [
        'id'             => (int) $c['id'],
        'comentario'     => $c['comentario'],
        'fecha_creacion' => $c['fecha_creacion'],
        'autor'          => trim($c['nombre'] . ' ' . $c['apellidos']),
        'foto_url'       => $c['foto']
                            ? uploads_url('profiles/' . $c['foto'])
                            : asset('img/avatar-default.svg'),
    ];
}

json_response($result);
