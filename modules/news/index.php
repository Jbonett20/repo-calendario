<?php
/**
 * =====================================================
 *  monchomania - Módulo Noticias
 *  - El superadmin crea publicaciones (imagen, video, canción, frase).
 *  - Todos los usuarios logueados pueden ver y comentar.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Visibilidad del módulo según configuración (solo aplica a usuarios normales)
if (!is_admin() && !module_is_visible('noticias')) {
    flash('error', 'El módulo de Noticias no está disponible actualmente.');
    redirect('modules/calendar/index.php');
}

$isAdmin = is_admin();

// Lista de publicaciones
$stmt = Database::connection()->query(
    "SELECT n.*, u.nombre, u.apellidos, u.foto AS autor_foto
       FROM noticias n
       JOIN usuarios u ON u.id = n.id_autor
      ORDER BY n.fecha_creacion DESC"
);
$noticias = $stmt->fetchAll();

/**
 * Devuelve el HTML del contenido multimedia según el tipo de publicación.
 */
function render_news_media(array $n): string
{
    $html = '';
    switch ($n['tipo']) {
        case 'imagen':
            if ($n['url_media']) {
                $html = '<div class="mm-news-media"><img src="' . e(uploads_url('news/' . $n['url_media'])) . '" alt="' . e($n['titulo']) . '"></div>';
            }
            break;

        case 'video':
            $url = $n['url_media'] ?? '';
            if ($url !== '') {
                // Detectar enlaces de YouTube y convertirlos en embeds
                if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]{6,})~', $url, $m)) {
                    $html = '<div class="ratio ratio-16x9"><iframe src="https://www.youtube.com/embed/' . e($m[1]) . '" allowfullscreen loading="lazy"></iframe></div>';
                } else {
                    $html = '<div class="mm-news-media"><video controls preload="metadata" src="' . e($url) . '"></video></div>';
                }
            }
            break;

        case 'cancion':
            if ($n['url_media']) {
                $html = '<div class="p-3"><audio controls preload="metadata" style="width:100%" src="' . e($n['url_media']) . '"></audio></div>';
            }
            break;

        case 'frase':
            $html = '<div class="p-4 mm-quote">“' . e($n['contenido_texto'] ?? '') . '”</div>';
            break;
    }
    return $html;
}

$pageTitle = 'Noticias';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">📰 Noticias</h1>
            <p class="text-muted mb-0">Lo último de la comunidad</p>
        </div>
        <?php if ($isAdmin): ?>
            <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#newPostForm" aria-expanded="false">
                ➕ Crear publicación
            </button>
        <?php endif; ?>
    </div>

    <?php if (flash_has('error')): ?>
        <div class="alert alert-danger"><?= e(flash('error')) ?></div>
    <?php endif; ?>
    <?php if (flash_has('success')): ?>
        <div class="alert alert-success" data-auto-dismiss><?= e(flash('success')) ?></div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <!-- Formulario de creación (solo superadmin) -->
        <div class="collapse mb-4" id="newPostForm">
            <div class="card mm-card shadow-sm">
                <div class="card-header mm-card-header">Nueva publicación</div>
                <div class="card-body">
                    <form method="post" action="<?= base_url('modules/news/save.php') ?>" enctype="multipart/form-data" novalidate>
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="titulo">Título *</label>
                                <input type="text" id="titulo" name="titulo" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="tipo">Tipo de publicación</label>
                                <select id="tipo" name="tipo" class="form-select" onchange="toggleNewsTypeFields(this.value)">
                                    <option value="frase">💬 Frase</option>
                                    <option value="imagen">🖼️ Imagen</option>
                                    <option value="video">🎬 Video</option>
                                    <option value="cancion">🎵 Canción / Audio</option>
                                </select>
                            </div>

                            <div class="col-12 news-field" data-type="frase">
                                <label class="form-label" for="contenido_texto">Texto de la frase *</label>
                                <textarea id="contenido_texto" name="contenido_texto" class="form-control" rows="2"></textarea>
                            </div>

                            <div class="col-12 news-field d-none" data-type="imagen">
                                <label class="form-label" for="imagen">Imagen *</label>
                                <input type="file" id="imagen" name="imagen" accept="image/*" class="form-control">
                            </div>

                            <div class="col-12 news-field d-none" data-type="video">
                                <label class="form-label" for="url_video">URL del video * (YouTube o enlace directo .mp4)</label>
                                <input type="text" id="url_video" name="url_media" class="form-control" placeholder="https://…">
                            </div>

                            <div class="col-12 news-field d-none" data-type="cancion">
                                <label class="form-label" for="url_cancion">URL del audio * (.mp3)</label>
                                <input type="text" id="url_cancion" name="url_media" class="form-control" placeholder="https://…">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">Publicar</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Lista de publicaciones -->
    <?php if (!$noticias): ?>
        <div class="text-center py-5">
            <div class="mm-feature-icon mx-auto mb-3">📭</div>
            <p class="text-muted">Todavía no hay publicaciones.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($noticias as $n): ?>
                <?php
                    $autorFoto = $n['autor_foto']
                        ? uploads_url('profiles/' . $n['autor_foto'])
                        : asset('img/avatar-default.svg');
                ?>
                <div class="col-lg-6">
                    <div class="card mm-card mm-news-card h-100">
                        <?= render_news_media($n) ?>
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <img src="<?= e($autorFoto) ?>" class="rounded-circle" width="36" height="36" style="object-fit:cover" alt="">
                                <div class="small">
                                    <strong><?= e($n['nombre'] . ' ' . $n['apellidos']) ?></strong>
                                    <div class="text-muted"><?= e(date('d/m/Y H:i', strtotime($n['fecha_creacion']))) ?></div>
                                </div>
                            </div>
                            <h2 class="h5"><?= e($n['titulo']) ?></h2>
                            <?php if ($n['tipo'] !== 'frase' && $n['contenido_texto']): ?>
                                <p class="text-muted mb-2"><?= e($n['contenido_texto']) ?></p>
                            <?php endif; ?>

                            <button class="btn btn-sm btn-outline-primary js-news-comments-toggle"
                                    data-noticia-id="<?= (int) $n['id'] ?>">
                                💬 Comentarios
                            </button>

                            <div class="js-news-comments-panel d-none mt-3" id="newsComments-<?= (int) $n['id'] ?>">
                                <div class="js-comments-list mm-comments mb-3"></div>
                                <form class="js-comment-form d-flex gap-2">
                                    <textarea rows="1" maxlength="500" class="form-control"
                                              placeholder="Escribe un comentario…" required></textarea>
                                    <button type="submit" class="btn btn-primary px-3">Enviar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
window.MM_NEWS_CONFIG = {
    getCommentsUrl: '<?= base_url('modules/news/get_comments.php') ?>',
    commentUrl:     '<?= base_url('modules/news/comment.php') ?>',
    csrfToken:      '<?= e(csrf_token()) ?>'
};
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
