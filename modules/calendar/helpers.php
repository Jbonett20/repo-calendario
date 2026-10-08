<?php
/**
 * =====================================================
 *  monchomania - Helpers de cumpleaños y saludos
 *  Funciones compartidas por el calendario, el listado
 *  de saludos y la vista pública de perfil.
 * =====================================================
 */

require_once __DIR__ . '/../../config/app.php';

/**
 * Devuelve los saludos (comentarios) que ha recibido una persona.
 *
 * @return array<int, array{id:int,autor_id:int,comentario:string,fecha_creacion:string,autor:string,foto_url:string}>
 */
function cumple_comments(int $destinoId): array
{
    if ($destinoId <= 0) {
        return [];
    }

    $stmt = Database::connection()->prepare(
        'SELECT c.id, c.id_usuario_autor, c.comentario, c.fecha_creacion,
                u.nombre, u.apellidos, u.foto
           FROM cumple_comentarios c
           JOIN usuarios u ON u.id = c.id_usuario_autor
          WHERE c.id_usuario_destino = ?
          ORDER BY c.fecha_creacion DESC'
    );
    $stmt->execute([$destinoId]);
    $filas = $stmt->fetchAll();

    $saludos = [];
    foreach ($filas as $c) {
        $saludos[] = [
            'id'             => (int) $c['id'],
            'autor_id'       => (int) $c['id_usuario_autor'],
            'comentario'     => $c['comentario'],
            'fecha_creacion' => $c['fecha_creacion'],
            'autor'          => trim($c['nombre'] . ' ' . $c['apellidos']),
            'foto_url'       => $c['foto']
                                    ? uploads_url('profiles/' . $c['foto'])
                                    : asset('img/avatar-default.svg'),
        ];
    }

    return $saludos;
}

/**
 * ¿Puede el usuario actual gestionar (editar/eliminar) un saludo?
 * Solo el propio autor o un administrador.
 */
function cumple_comment_can_manage(int $autorId, int $currentUserId, bool $esAdmin): bool
{
    return $esAdmin || ($currentUserId > 0 && $autorId === $currentUserId);
}

/**
 * Botones (HTML) de editar/eliminar para un saludo renderizado en el servidor.
 * Se basan en las clases .js-comment-edit y .js-comment-delete que activa main.js.
 */
function cumple_comment_actions_html(array $saludo, int $currentUserId, bool $esAdmin): string
{
    $autorId = (int) ($saludo['autor_id'] ?? 0);
    if (!cumple_comment_can_manage($autorId, $currentUserId, $esAdmin)) {
        return '';
    }

    $esAutor = $currentUserId > 0 && $autorId === $currentUserId;
    $html    = '';

    if ($esAutor) {
        $html .= '<button type="button" class="btn btn-sm btn-outline-secondary js-comment-edit"'
            . ' data-comment-id="' . (int) $saludo['id'] . '"'
            . ' data-edit-url="' . e(base_url('modules/calendar/edit_comment.php')) . '"'
            . ' title="Editar saludo">✏️</button>';
    }

    $html .= '<button type="button" class="btn btn-sm btn-outline-danger js-comment-delete"'
        . ' data-comment-id="' . (int) $saludo['id'] . '"'
        . ' data-delete-url="' . e(base_url('modules/calendar/delete_comment.php')) . '"'
        . ' title="Eliminar saludo">🗑️</button>';

    return $html;
}

/**
 * Información del próximo cumpleaños de una persona.
 *
 * @return array{fecha:DateTime,dias:int,edad:int,es_hoy:bool}|null
 */
function cumple_proximo(string $fechaNacimiento, ?DateTime $hoy = null): ?array
{
    if ($fechaNacimiento === '') {
        return null;
    }

    $partes = explode('-', $fechaNacimiento);
    if (count($partes) < 3) {
        return null;
    }

    $hoy  = $hoy ?: new DateTime('today');
    $mes  = (int) $partes[1];
    $dia  = (int) $partes[2];
    if ($mes < 1 || $mes > 12 || $dia < 1 || $dia > 31) {
        return null;
    }

    $proximo = new DateTime($hoy->format('Y') . '-' . $partes[1] . '-' . $partes[2]);
    if ($proximo < $hoy) {
        $proximo->modify('+1 year');
    }

    $dias  = (int) $hoy->diff($proximo)->days;
    $edad  = (int) $proximo->format('Y') - (int) $partes[0];

    return [
        'fecha'  => $proximo,
        'dias'   => $dias,
        'edad'   => $edad,
        'es_hoy' => $dias === 0,
    ];
}
