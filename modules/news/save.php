<?php
/**
 * =====================================================
 *  monchomania - Guardar nueva publicación (solo superadmin)
 *  - Imagen / Frase: como antes.
 *  - Video: se sube desde el equipo O se comparte un enlace
 *    (YouTube/Vimeo o archivo directo .mp4/.webm).
 *  - Audio: solo se sube desde el equipo.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';

if (!is_admin()) {
    flash('error', 'Solo el administrador puede crear publicaciones.');
    redirect('modules/news/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/news/index.php');
}

// Si el POST supera post_max_size, PHP deja $_POST y $_FILES vacíos.
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'El archivo es demasiado grande para el servidor (máximo: '
        . ini_get('post_max_size') . ').');
    redirect('modules/news/index.php');
}

if (!verify_csrf_request()) {
    flash('error', 'Token de seguridad inválido.');
    redirect('modules/news/index.php');
}

$titulo      = limpiar_texto($_POST['titulo'] ?? '', 200);
$tipo        = $_POST['tipo'] ?? 'frase';
$contenido   = limpiar_texto($_POST['contenido_texto'] ?? '', 2000);
$url_video   = trim((string) ($_POST['url_video'] ?? ''));
$videoOrigen = ($_POST['video_origen'] ?? 'subir') === 'enlace' ? 'enlace' : 'subir';

if (!in_array($tipo, ['imagen', 'video', 'cancion', 'frase'], true)) {
    $tipo = 'frase';
}

$errores = [];
if ($titulo === '') {
    $errores[] = 'El título es obligatorio.';
}

$archivo = null;   // nombre del archivo subido en /uploads/news
$enlace  = null;   // URL externa del video

switch ($tipo) {
    case 'imagen':
        if (empty($_FILES['imagen']['name'])) {
            $errores[] = 'Debes seleccionar una imagen.';
        } else {
            try {
                $archivo = handle_photo_upload($_FILES['imagen'], 'news');
            } catch (RuntimeException $e) {
                $errores[] = $e->getMessage();
            }
        }
        break;

    case 'video':
        if ($videoOrigen === 'enlace') {
            if ($url_video === '') {
                $errores[] = 'Pega el enlace del video.';
            } elseif (!filter_var($url_video, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $url_video)) {
                $errores[] = 'El enlace del video no es válido (debe empezar por http:// o https://).';
            } elseif (news_embed_url($url_video) === null && !news_link_is_video_file($url_video)) {
                $errores[] = 'Solo se admiten enlaces de YouTube o Vimeo, o archivos de video directos (.mp4, .webm).';
            } else {
                $enlace = $url_video;
            }
        } elseif (empty($_FILES['video_file']['name'])) {
            $errores[] = 'Selecciona el archivo de video o cambia a "Compartir enlace".';
        } else {
            try {
                $archivo = handle_media_upload($_FILES['video_file'], 'video');
            } catch (RuntimeException $e) {
                $errores[] = $e->getMessage();
            }
        }
        break;

    case 'cancion':
        if (empty($_FILES['audio_file']['name'])) {
            $errores[] = 'Debes subir un archivo de audio desde tu equipo.';
        } else {
            try {
                $archivo = handle_media_upload($_FILES['audio_file'], 'audio');
            } catch (RuntimeException $e) {
                $errores[] = $e->getMessage();
            }
        }
        break;

    case 'frase':
        if ($contenido === '') {
            $errores[] = 'Escribe el texto de la frase.';
        }
        break;
}

if ($errores) {
    flash('error', implode(' ', $errores));
    redirect('modules/news/index.php');
}

$medio = ($archivo !== null && $archivo !== '') ? $archivo : $enlace;

$stmt = Database::connection()->prepare(
    'INSERT INTO noticias (titulo, tipo, contenido_texto, url_media, id_autor)
     VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([
    $titulo,
    $tipo,
    $contenido !== '' ? $contenido : null,
    $medio,
    $user['id'],
]);

$noticiaId = (int) Database::connection()->lastInsertId();

// Avisar a toda la comunidad de la nueva publicación
notify_all(
    'noticia',
    'Nueva publicación: ' . $titulo,
    base_url('modules/news/detail.php?id=' . $noticiaId),
    (int) $user['id']
);

flash('success', 'Publicación creada correctamente.');
redirect('modules/news/index.php');
