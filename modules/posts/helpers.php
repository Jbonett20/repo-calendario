<?php
/**
 * =====================================================
 *  monchomania - Utilidades del módulo Publicaciones
 *  Lo usan el feed (index.php), las APIs de "me gusta"
 *  y la de comentarios.
 * =====================================================
 */

/**
 * Tabla y columna donde viven los "me gusta" según el tipo de contenido.
 * Se usan listas blancas para que el nombre de la tabla nunca venga del usuario.
 *
 * @return array{tabla:string,columna:string}|null
 */
function posts_likes_source(string $tipo): ?array
{
    $fuentes = [
        'publicacion' => ['tabla' => 'publicaciones_likes', 'columna' => 'id_publicacion'],
        'comentario'  => ['tabla' => 'publicaciones_comentarios_likes', 'columna' => 'id_comentario'],
    ];

    return $fuentes[$tipo] ?? null;
}

/** URL de la imagen de una publicación (vacío si no tiene). */
function posts_image_src(?string $imagen): string
{
    if ($imagen === null || $imagen === '') {
        return '';
    }

    return uploads_url('posts/' . $imagen);
}

/** URL del avatar de un usuario (o el avatar por defecto). */
function posts_avatar_url(?string $foto): string
{
    return $foto ? uploads_url('profiles/' . $foto) : asset('img/avatar-default.svg');
}

/** ¿Puede el usuario actual gestionar (borrar) este contenido? */
function posts_can_manage(int $autorId, int $currentUserId, bool $esAdmin): bool
{
    return $esAdmin || ($currentUserId > 0 && $autorId === $currentUserId);
}

/**
 * Comprueba el acceso a una API del módulo: módulo visible, método POST
 * y token CSRF válido. Responde con JSON y detiene el script si algo falla.
 */
function posts_api_guard(): void
{
    if (!is_admin() && !module_is_visible('publicaciones')) {
        json_response(['error' => 'El módulo de Publicaciones no está disponible.'], 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'Método no permitido.'], 405);
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || $token === ''
        || !hash_equals($_SESSION['csrf_token'], $token)) {
        json_response(['error' => 'Token de seguridad inválido.'], 403);
    }
}

/** Lee el cuerpo JSON de una petición de la API del módulo. */
function posts_json_payload(): array
{
    $payload = json_decode(file_get_contents('php://input'), true);

    return is_array($payload) ? $payload : [];
}

/**
 * Estado completo de los "me gusta" de una publicación o de un comentario.
 *
 * @return array{likers:array<int,array{id:int,nombre:string,foto_url:string}>,total:int,liked:bool,resumen:string}
 */
function posts_like_state(PDO $pdo, string $tipo, int $id, int $userId): array
{
    $likers = posts_likers($pdo, $tipo, $id);
    $liked  = false;

    foreach ($likers as $persona) {
        if ((int) $persona['id'] === $userId) {
            $liked = true;
            break;
        }
    }

    return [
        'likers'  => $likers,
        'total'   => count($likers),
        'liked'   => $liked,
        'resumen' => posts_likes_summary($likers, $userId),
    ];
}

/**
 * Personas que dieron "me gusta" (de la primera a la última).
 *
 * @return array<int, array{id:int,nombre:string,foto_url:string}>
 */
function posts_likers(PDO $pdo, string $tipo, int $id): array
{
    $fuente = posts_likes_source($tipo);
    if ($fuente === null || $id <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT u.id, u.nombre, u.apellidos, u.foto
           FROM `' . $fuente['tabla'] . '` l
           JOIN usuarios u ON u.id = l.id_usuario
          WHERE l.`' . $fuente['columna'] . '` = ?
          ORDER BY l.fecha_creacion ASC'
    );
    $stmt->execute([$id]);

    $personas = [];
    foreach ($stmt->fetchAll() as $fila) {
        $personas[] = [
            'id'       => (int) $fila['id'],
            'nombre'   => trim($fila['nombre'] . ' ' . $fila['apellidos']),
            'foto_url' => posts_avatar_url($fila['foto']),
        ];
    }

    return $personas;
}

/** Frase corta con quiénes dieron "me gusta" ("Te gusta y a 2 personas más."). */
function posts_likes_summary(array $personas, int $userId): string
{
    $total   = count($personas);
    $teGusta = false;
    $nombres = [];

    foreach ($personas as $persona) {
        if ((int) $persona['id'] === $userId) {
            $teGusta = true;
            continue;
        }
        $nombres[] = (string) $persona['nombre'];
    }

    if ($total === 0) {
        return 'Sé el primero en dar me gusta.';
    }
    if ($teGusta) {
        $otros = $total - 1;
        if ($otros === 0) {
            return 'Te gusta esto.';
        }
        return 'Te gusta y a ' . $otros . ($otros === 1 ? ' persona más.' : ' personas más.');
    }
    if ($total === 1) {
        return 'A ' . $nombres[0] . ' le gusta esto.';
    }

    $mostrados = array_slice($nombres, 0, 3);
    $resto     = $total - count($mostrados);
    $texto     = implode(', ', $mostrados);
    if ($resto > 0) {
        $texto .= ' y ' . $resto . ' más';
    }

    return 'A ' . $texto . ' les gusta esto.';
}

/**
 * Lista HTML de personas para el modal "A quién le gusta".
 *
 * @param array<int, array{id:int,nombre:string,foto_url:string}> $personas
 */
function posts_likers_html(array $personas): string
{
    if (!$personas) {
        return '<p class="text-muted small mb-0">Todavía nadie ha dado me gusta.</p>';
    }

    $html = '<ul class="mm-likers-list">';
    foreach ($personas as $persona) {
        $html .= '<li>'
            . '<img src="' . e($persona['foto_url']) . '" class="js-profile-photo" alt="">'
            . '<span>' . e($persona['nombre']) . '</span>'
            . '</li>';
    }
    $html .= '</ul>';

    return $html;
}

/** Comentarios de una publicación (del más reciente al más antiguo). */
function posts_comments(PDO $pdo, int $publicacionId): array
{
    if ($publicacionId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT c.id, c.id_usuario, c.id_comentario_padre, c.comentario, c.fecha_creacion,
                u.nombre, u.apellidos, u.foto
           FROM publicaciones_comentarios c
           JOIN usuarios u ON u.id = c.id_usuario
          WHERE c.id_publicacion = ?
          ORDER BY c.fecha_creacion DESC'
    );
    $stmt->execute([$publicacionId]);

    return $stmt->fetchAll();
}

/* =====================================================
 *  FEED PAGINADO (de la más reciente a la más antigua)
 * ===================================================== */

/** Cuántas publicaciones se muestran antes de "Ver más". */
function posts_page_size(): int
{
    return 10;
}

/**
 * Página del feed: primero las más recientes.
 * Se pide una fila de más para saber si quedan publicaciones.
 *
 * @return array<int, array<string,mixed>>
 */
function posts_feed(PDO $pdo, int $limit, int $offset = 0): array
{
    $stmt = $pdo->prepare(
        'SELECT p.*, u.nombre, u.apellidos, u.foto AS autor_foto,
                (SELECT COUNT(*) FROM publicaciones_comentarios c WHERE c.id_publicacion = p.id) AS total_comentarios
           FROM publicaciones p
           JOIN usuarios u ON u.id = p.id_autor
          ORDER BY p.fecha_creacion DESC, p.id DESC
          LIMIT ? OFFSET ?'
    );
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->bindValue(2, max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/** HTML de una tarjeta del feed. */
function posts_render_card(array $p, array $estado, int $userId, bool $esAdmin): string
{
    $publicacionId  = (int) $p['id'];
    $imagenSrc      = posts_image_src($p['imagen']);
    $puedeGestionar = posts_can_manage((int) $p['id_autor'], $userId, $esAdmin);

    ob_start();
    ?>
    <article class="card mm-card mm-post shadow-sm mb-3" id="post-<?= $publicacionId ?>">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-3">
                <img src="<?= e(posts_avatar_url($p['autor_foto'])) ?>" class="mm-post-avatar js-profile-photo" alt="">
                <div class="flex-grow-1">
                    <div class="fw-semibold"><?= e($p['nombre'] . ' ' . $p['apellidos']) ?></div>
                    <time class="text-muted small" datetime="<?= e(fecha_iso($p['fecha_creacion'])) ?>">
                        <?= e(fecha_hora_local($p['fecha_creacion'])) ?>
                    </time>
                </div>
                <?php if ($puedeGestionar): ?>
                    <form method="post" action="<?= base_url('modules/posts/delete.php') ?>"
                          onsubmit="return confirm('¿Eliminar esta publicación con sus comentarios y &quot;me gusta&quot;?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $publicacionId ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                title="Eliminar publicación" aria-label="Eliminar publicación">🗑️</button>
                    </form>
                <?php endif; ?>
            </div>

            <p class="mm-post-text mb-3"><?= nl2br(e($p['contenido'])) ?></p>

            <?php if ($imagenSrc !== ''): ?>
                <img src="<?= e($imagenSrc) ?>" class="mm-post-media mb-3" loading="lazy"
                     draggable="false" alt="Imagen de la publicación de <?= e($p['nombre']) ?>">
            <?php endif; ?>

            <div class="d-flex align-items-center gap-2 flex-wrap mm-post-actions">
                <button type="button"
                        class="btn btn-sm mm-like-btn <?= $estado['liked'] ? 'btn-primary active' : 'btn-outline-primary' ?> js-post-like"
                        data-publicacion-id="<?= $publicacionId ?>"
                        aria-pressed="<?= $estado['liked'] ? 'true' : 'false' ?>">
                    ❤️ Me gusta (<span class="js-like-count"><?= $estado['total'] ?></span>)
                </button>

                <button type="button" class="btn btn-sm btn-outline-secondary js-likers"
                        data-likers-tipo="publicacion" data-likers-id="<?= $publicacionId ?>"
                        data-bs-toggle="modal" data-bs-target="#likersModal">
                    👀 Ver quién
                </button>

                <button type="button" class="btn btn-sm btn-outline-secondary js-toggle-comments"
                        data-comments-target="#comments-<?= $publicacionId ?>" aria-expanded="false">
                    💬 Comentarios (<span class="js-comments-count"><?= (int) $p['total_comentarios'] ?></span>)
                </button>

                <span class="mm-like-summary js-like-summary"><?= e($estado['resumen']) ?></span>
            </div>

            <div class="mm-post-comments d-none mt-3" id="comments-<?= $publicacionId ?>"
                 data-publicacion-id="<?= $publicacionId ?>">
                <div class="mm-comments js-comments-list mb-3">
                    <p class="text-muted small mb-0">Cargando comentarios…</p>
                </div>
                <form class="js-comment-form d-flex gap-2">
                    <label class="visually-hidden" for="comment-<?= $publicacionId ?>">Escribe un comentario</label>
                    <textarea id="comment-<?= $publicacionId ?>" rows="1" maxlength="500" class="form-control"
                              placeholder="Escribe un comentario…" data-emoji required></textarea>
                    <button type="submit" class="btn btn-primary px-3">Enviar</button>
                </form>
            </div>
        </div>
    </article>
    <?php

    return (string) ob_get_clean();
}

/**
 * HTML de una página del feed y si quedan más publicaciones.
 *
 * @return array{html:string,hay_mas:bool,mostradas:int}
 */
function posts_render_page(PDO $pdo, int $userId, bool $esAdmin, int $offset, int $pageSize): array
{
    $filas  = posts_feed($pdo, $pageSize + 1, $offset);
    $hayMas = count($filas) > $pageSize;

    if ($hayMas) {
        $filas = array_slice($filas, 0, $pageSize);
    }

    $html = '';
    foreach ($filas as $p) {
        $estado = posts_like_state($pdo, 'publicacion', (int) $p['id'], $userId);
        $html  .= posts_render_card($p, $estado, $userId, $esAdmin);
    }

    return ['html' => $html, 'hay_mas' => $hayMas, 'mostradas' => count($filas)];
}
