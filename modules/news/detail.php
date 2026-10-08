<?php
/**
 * =====================================================
 *  monchomania - Detalle de una publicación de Noticias
 *  Muestra el medio completo, los "me gusta" y los comentarios.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';

// Visibilidad del módulo según configuración (solo aplica a usuarios normales)
if (!is_admin() && !module_is_visible('noticias')) {
    flash('error', 'El módulo de Noticias no está disponible actualmente.');
    redirect('modules/calendar/index.php');
}

$noticiaId = (int) ($_GET['id'] ?? 0);
if ($noticiaId <= 0) {
    flash('error', 'Publicación no válida.');
    redirect('modules/news/index.php');
}

ensure_app_tables();

$pdo  = Database::connection();
$stmt = $pdo->prepare(
    'SELECT n.*, u.nombre, u.apellidos, u.foto AS autor_foto
       FROM noticias n
       JOIN usuarios u ON u.id = n.id_autor
      WHERE n.id = ? LIMIT 1'
);
$stmt->execute([$noticiaId]);
$n = $stmt->fetch();

if (!$n) {
    flash('error', 'La publicación no existe.');
    redirect('modules/news/index.php');
}

$userId           = (int) $user['id'];
$totalLikes       = news_likes_count($pdo, $noticiaId);
$meGusta          = news_user_liked($pdo, $noticiaId, $userId);
$resumenLikes     = news_likes_summary($pdo, $noticiaId, $userId);
$autorFoto        = $n['autor_foto'] ? uploads_url('profiles/' . $n['autor_foto']) : asset('img/avatar-default.svg');

$stmt = $pdo->prepare('SELECT COUNT(*) FROM noticias_comentarios WHERE id_noticia = ?');
$stmt->execute([$noticiaId]);
$totalComentarios = (int) $stmt->fetchColumn();

$pageTitle = $n['titulo'];
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-4">
    <a class="mm-back-link d-inline-block mb-3" href="<?= base_url('modules/news/index.php') ?>">
        ← Volver a Noticias
    </a>

    <article class="card mm-card mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                <span class="badge mm-news-type-badge">
                    <?= news_type_icon($n['tipo']) ?> <?= e(news_type_label($n['tipo'])) ?>
                </span>
                <time class="text-muted small" datetime="<?= e(fecha_iso($n['fecha_creacion'])) ?>">
                    <?= e(fecha_hora_local($n['fecha_creacion'])) ?>
                </time>
                <?php if (is_admin()): ?>
                    <form method="post" action="<?= base_url('modules/news/delete.php') ?>" class="ms-auto"
                          onsubmit="return confirm('¿Eliminar esta publicación con todos sus comentarios y &quot;me gusta&quot;?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">🗑️ Eliminar publicación</button>
                    </form>
                <?php endif; ?>
            </div>

            <h1 class="h4 mb-3"><?= e($n['titulo']) ?></h1>

            <div class="d-flex align-items-center gap-2 mb-3">
                <img src="<?= e($autorFoto) ?>" class="mm-news-author-avatar" alt="">
                <div class="small">
                    <strong><?= e($n['nombre'] . ' ' . $n['apellidos']) ?></strong>
                </div>
            </div>

            <?php if ($n['tipo'] !== 'frase' && trim((string) $n['contenido_texto']) !== ''): ?>
                <p class="mb-3"><?= nl2br(e($n['contenido_texto'])) ?></p>
            <?php endif; ?>

            <?= render_news_media($n) ?>

            <div class="d-flex align-items-center gap-3 flex-wrap mt-3">
                <button type="button"
                        class="btn mm-like-btn <?= $meGusta ? 'btn-primary active' : 'btn-outline-primary' ?> js-news-like"
                        data-noticia-id="<?= (int) $n['id'] ?>"
                        aria-pressed="<?= $meGusta ? 'true' : 'false' ?>">
                    ❤️ Me gusta (<span class="js-like-count"><?= $totalLikes ?></span>)
                </button>
                <span class="mm-like-summary js-like-summary"><?= e($resumenLikes) ?></span>
            </div>
        </div>
    </article>

    <section class="card mm-card">
        <div class="card-header mm-card-header">
            💬 Comentarios (<span class="js-comments-count"><?= $totalComentarios ?></span>)
        </div>
        <div class="card-body js-news-comments" data-noticia-id="<?= (int) $n['id'] ?>">
            <div class="js-comments-list mm-comments mb-3">
                <p class="text-muted small mb-0">Cargando comentarios…</p>
            </div>
            <form class="js-comment-form d-flex gap-2">
                <textarea rows="1" maxlength="500" class="form-control"
                          placeholder="Escribe un comentario…" required></textarea>
                <button type="submit" class="btn btn-primary px-3">Enviar</button>
            </form>
        </div>
    </section>
</div>

<script>
window.MM_COMMENT_DELETE = {
    url:     '<?= base_url('modules/news/delete_comment.php') ?>',
    isAdmin: <?= is_admin() ? 'true' : 'false' ?>
};
window.MM_NEWS_CONFIG = {
    getCommentsUrl: '<?= base_url('modules/news/get_comments.php') ?>',
    commentUrl:     '<?= base_url('modules/news/comment.php') ?>',
    likeUrl:        '<?= base_url('modules/news/like.php') ?>',
    csrfToken:      '<?= e(csrf_token()) ?>'
};
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
