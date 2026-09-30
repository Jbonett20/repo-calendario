<?php
/**
 * =====================================================
 *  monchomania - Guardar nueva publicación (solo superadmin)
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';

if (!is_admin()) {
    flash('error', 'Solo el administrador puede crear publicaciones.');
    redirect('modules/news/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/news/index.php');
}

if (!verify_csrf_request()) {
    flash('error', 'Token de seguridad inválido.');
    redirect('modules/news/index.php');
}

$titulo   = trim($_POST['titulo'] ?? '');
$tipo     = $_POST['tipo'] ?? 'frase';
$contenido = trim($_POST['contenido_texto'] ?? '');
$url_media = trim($_POST['url_media'] ?? '');

if (!in_array($tipo, ['imagen', 'video', 'cancion', 'frase'], true)) {
    $tipo = 'frase';
}

$errores = [];
if ($titulo === '') {
    $errores[] = 'El título es obligatorio.';
}

$archivo = null;
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
    case 'cancion':
        if ($url_media === '' || !filter_var($url_media, FILTER_VALIDATE_URL)) {
            $errores[] = 'Debes indicar una URL válida del medio.';
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

$stmt = Database::connection()->prepare(
    'INSERT INTO noticias (titulo, tipo, contenido_texto, url_media, id_autor)
     VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([
    $titulo,
    $tipo,
    $contenido !== '' ? $contenido : null,
    $archivo !== null ? $archivo : ($url_media !== '' ? $url_media : null),
    $user['id'],
]);

flash('success', 'Publicación creada correctamente.');
redirect('modules/news/index.php');
