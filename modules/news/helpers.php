<?php
/**
 * =====================================================
 *  monchomania - Utilidades compartidas del módulo Noticias
 *  Lo usan el listado (index.php), el detalle (detail.php)
 *  y la API de "me gusta" (like.php).
 * =====================================================
 */

/** ¿El medio guardado es un enlace externo (true) o un archivo subido (false)? */
function news_media_is_link(?string $url): bool
{
    return $url !== null && preg_match('~^https?://~i', $url) === 1;
}

/** URL final del medio: enlace externo o archivo dentro de /uploads/news. */
function news_media_src(?string $url): string
{
    if ($url === null || $url === '') {
        return '';
    }
    return news_media_is_link($url) ? $url : uploads_url('news/' . $url);
}

/** Etiqueta legible del tipo de publicación. */
function news_type_label(string $tipo): string
{
    $labels = [
        'imagen'  => 'Imagen',
        'video'   => 'Video',
        'cancion' => 'Audio',
        'frase'   => 'Frase',
    ];
    return $labels[$tipo] ?? 'Publicación';
}

/** Icono del tipo de publicación. */
function news_type_icon(string $tipo): string
{
    $icons = [
        'imagen'  => '🖼️',
        'video'   => '🎬',
        'cancion' => '🎵',
        'frase'   => '💬',
    ];
    return $icons[$tipo] ?? '📰';
}

/**
 * Atributos del reproductor que ocultan la opción de descargar el medio
 * (botón de descarga del navegador y menú contextual).
 */
function news_no_download_attrs(): string
{
    return 'controls controlsList="nodownload noremoteplayback" disablePictureInPicture '
        . 'oncontextmenu="return false" draggable="false"';
}

/** Devuelve la URL de incrustación si el enlace es de YouTube o Vimeo. */
function news_embed_url(string $url): ?string
{
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,})~i', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~i', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return null;
}

/** ¿El enlace apunta directamente a un archivo de video reproducible? */
function news_link_is_video_file(string $url): bool
{
    return preg_match('~\.(mp4|webm|ogv|ogg)(\?.*)?$~i', $url) === 1;
}

/** Texto corto de la publicación para el listado. */
function news_summary(array $n): string
{
    $texto = trim((string) ($n['contenido_texto'] ?? ''));
    if ($texto !== '') {
        return $texto;
    }

    $porTipo = [
        'imagen'  => 'Imagen publicada por la comunidad.',
        'video'   => 'Video publicado por la comunidad.',
        'cancion' => 'Audio publicado por la comunidad.',
    ];
    return $porTipo[$n['tipo']] ?? '';
}

/**
 * HTML del contenido multimedia completo de una publicación.
 * Los videos y audios subidos se reproducen sin opción de descarga;
 * los enlaces de YouTube/Vimeo se incrustan.
 */
function render_news_media(array $n): string
{
    $url = $n['url_media'] ?? '';

    switch ($n['tipo']) {
        case 'imagen':
            if ($url === '') {
                return '';
            }
            return '<div class="mm-news-media"><img src="' . e(news_media_src($url)) . '" alt="'
                . e($n['titulo']) . '" draggable="false"></div>';

        case 'video':
            if ($url === '') {
                return '';
            }
            if (news_media_is_link($url)) {
                $embed = news_embed_url($url);
                if ($embed !== null) {
                    return '<div class="ratio ratio-16x9"><iframe src="' . e($embed) . '" title="'
                        . e($n['titulo']) . '" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"'
                        . ' allowfullscreen loading="lazy"></iframe></div>';
                }
            }
            return '<div class="mm-news-media"><video ' . news_no_download_attrs() . ' preload="metadata" src="'
                . e(news_media_src($url)) . '"></video></div>';

        case 'cancion':
            if ($url === '') {
                return '';
            }
            return '<div class="mm-news-audio"><audio ' . news_no_download_attrs() . ' preload="metadata" src="'
                . e(news_media_src($url)) . '"></audio></div>';

        case 'frase':
            return '<div class="p-4 mm-quote">“' . e($n['contenido_texto'] ?? '') . '”</div>';
    }

    return '';
}

/** Cantidad de "me gusta" de una publicación. */
function news_likes_count(PDO $pdo, int $noticiaId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM noticias_likes WHERE id_noticia = ?');
    $stmt->execute([$noticiaId]);
    return (int) $stmt->fetchColumn();
}

/** ¿El usuario indicado ya le dio "me gusta" a la publicación? */
function news_user_liked(PDO $pdo, int $noticiaId, int $userId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM noticias_likes WHERE id_noticia = ? AND id_usuario = ? LIMIT 1');
    $stmt->execute([$noticiaId, $userId]);
    return $stmt->fetchColumn() !== false;
}

/** Frase corta con quiénes dieron "me gusta" ("Te gusta y a 2 personas más."). */
function news_likes_summary(PDO $pdo, int $noticiaId, int $userId): string
{
    $stmt = $pdo->prepare(
        'SELECT l.id_usuario, u.nombre
           FROM noticias_likes l
           JOIN usuarios u ON u.id = l.id_usuario
          WHERE l.id_noticia = ?
          ORDER BY l.fecha_creacion ASC'
    );
    $stmt->execute([$noticiaId]);
    $filas = $stmt->fetchAll();

    $total   = count($filas);
    $teGusta = false;
    $nombres = [];
    foreach ($filas as $fila) {
        if ((int) $fila['id_usuario'] === $userId) {
            $teGusta = true;
            continue;
        }
        $nombres[] = (string) $fila['nombre'];
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
