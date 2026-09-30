<?php
/**
 * =====================================================
 *  monchomania - API: comentarios de una noticia (JSON)
 *  Parámetro GET: noticia_id
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';

$noticiaId = (int) ($_GET['noticia_id'] ?? 0);
if ($noticiaId <= 0) {
    json_response(['error' => 'Identificador de noticia inválido.'], 400);
}

$stmt = Database::connection()->prepare(
    "SELECT c.id, c.comentario, c.fecha_creacion,
            u.nombre, u.apellidos, u.foto
       FROM noticias_comentarios c
       JOIN usuarios u ON u.id = c.id_usuario
      WHERE c.id_noticia = ?
      ORDER BY c.fecha_creacion DESC"
);
$stmt->execute([$noticiaId]);
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
