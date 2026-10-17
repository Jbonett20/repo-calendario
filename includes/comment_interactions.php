<?php
/**
 * Helpers para interacciones comunes en comentarios de noticias,
 * publicaciones y saludos de cumpleaños.
 */

function comment_interaction_source(string $tipo): ?array
{
    $sources = [
        'noticia' => [
            'comments' => 'noticias_comentarios',
            'likes' => 'noticias_comentarios_likes',
            'target' => 'id_noticia',
            'author' => 'id_usuario',
            'module' => 'noticias',
        ],
        'publicacion' => [
            'comments' => 'publicaciones_comentarios',
            'likes' => 'publicaciones_comentarios_likes',
            'target' => 'id_publicacion',
            'author' => 'id_usuario',
            'module' => 'publicaciones',
        ],
        'saludo' => [
            'comments' => 'cumple_comentarios',
            'likes' => 'cumple_comentarios_likes',
            'target' => 'id_usuario_destino',
            'author' => 'id_usuario_autor',
            'module' => 'calendario',
        ],
    ];

    return $sources[$tipo] ?? null;
}

function comment_interaction_access(string $tipo): bool
{
    $source = comment_interaction_source($tipo);
    return $source !== null
        && ($tipo === 'saludo' || is_admin() || module_is_visible($source['module']));
}

function comment_interaction_csrf_valid(): bool
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return isset($_SESSION['csrf_token']) && is_string($token) && $token !== ''
        && hash_equals($_SESSION['csrf_token'], $token);
}

function comment_interaction_list(PDO $pdo, string $tipo, int $targetId, int $userId): array
{
    $source = comment_interaction_source($tipo);
    if ($source === null || $targetId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT c.id, c.`' . $source['author'] . '` AS autor_id, c.id_comentario_padre,
                c.comentario, c.fecha_creacion, u.nombre, u.apellidos, u.foto,
                (SELECT COUNT(*) FROM `' . $source['likes'] . '` l
                  WHERE l.id_comentario = c.id) AS likes_total,
                EXISTS(SELECT 1 FROM `' . $source['likes'] . '` l
                        WHERE l.id_comentario = c.id AND l.id_usuario = ?) AS liked
           FROM `' . $source['comments'] . '` c
           JOIN usuarios u ON u.id = c.`' . $source['author'] . '`
          WHERE c.`' . $source['target'] . '` = ?
          ORDER BY COALESCE(c.id_comentario_padre, c.id) DESC,
                   (c.id_comentario_padre IS NOT NULL) ASC, c.fecha_creacion ASC'
    );
    $stmt->execute([$userId, $targetId]);

    $comments = [];
    foreach ($stmt->fetchAll() as $comment) {
        $comments[] = [
            'id' => (int) $comment['id'],
            'autor_id' => (int) $comment['autor_id'],
            'parent_id' => $comment['id_comentario_padre'] === null
                ? null
                : (int) $comment['id_comentario_padre'],
            'comentario' => $comment['comentario'],
            'fecha_creacion' => $comment['fecha_creacion'],
            'autor' => trim($comment['nombre'] . ' ' . $comment['apellidos']),
            'foto_url' => $comment['foto']
                ? uploads_url('profiles/' . $comment['foto'])
                : asset('img/avatar-default.svg'),
            'likes_total' => (int) $comment['likes_total'],
            'liked' => (bool) $comment['liked'],
        ];
    }
    return $comments;
}
