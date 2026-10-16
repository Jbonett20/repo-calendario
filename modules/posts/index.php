<?php
/**
 * =====================================================
 *  monchomania - Módulo Publicaciones (feed de la comunidad)
 *  - Cualquier usuario activo publica un estado (texto + imagen opcional).
 *  - Todos pueden dar "me gusta" (y ver quién lo dio) y comentar.
 *  - Cada comentario también recibe "me gusta".
 *  - El autor borra lo suyo; el superadmin borra cualquier cosa.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';

// Visibilidad del módulo según configuración (solo aplica a usuarios normales)
if (!is_admin() && !module_is_visible('publicaciones')) {
    flash('error', 'El módulo de Publicaciones no está disponible actualmente.');
    redirect('modules/calendar/index.php');
}

ensure_app_tables();

$pdo     = Database::connection();
$userId  = (int) $user['id'];
$esAdmin = is_admin();

// Primera página del feed: las publicaciones más recientes (10 por página).
$pagina = posts_render_page($pdo, $userId, $esAdmin, 0, posts_page_size());
$hayMas = $pagina['hay_mas'];

$pageTitle = 'Publicaciones';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-4">
    <div class="mb-4">
        <h1 class="h3 mb-0">📝 Publicaciones</h1>
        <p class="text-muted mb-0">Comparte lo que quieras con la comunidad</p>
    </div>

    <?php if (flash_has('error')): ?>
        <div class="alert alert-danger"><?= e(flash('error')) ?></div>
    <?php endif; ?>
    <?php if (flash_has('success')): ?>
        <div class="alert alert-success" data-auto-dismiss><?= e(flash('success')) ?></div>
    <?php endif; ?>

    <!-- Formulario para publicar (todos los usuarios activos) -->
    <section class="card mm-card shadow-sm mb-4">
        <div class="card-body">
            <form method="post" action="<?= base_url('modules/posts/save.php') ?>"
                  enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <div class="d-flex gap-3">
                    <img src="<?= e(posts_avatar_url($user['foto'])) ?>" class="mm-post-avatar" alt="">
                    <div class="flex-grow-1">
                        <label class="visually-hidden" for="contenido">¿Qué quieres compartir?</label>
                        <textarea id="contenido" name="contenido" class="form-control mb-2" rows="2"
                                  maxlength="2000" placeholder="¿Qué quieres compartir con la comunidad?"
                                  data-emoji required></textarea>

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <input type="file" id="postImagen" name="imagen" accept="image/*"
                                   class="form-control form-control-sm photo-input js-post-image"
                                   data-preview="#postPreview">
                            <button type="submit" class="btn btn-primary ms-auto px-4">📤 Publicar</button>
                        </div>
                        <div class="form-text">
                            Imagen opcional · <?= photo_formats_label() ?> · máx. <?= photo_max_mb() ?> MB
                        </div>

                        <div class="mm-post-preview d-none mt-2" id="postPreviewWrap">
                            <img id="postPreview" alt="Vista previa de la imagen">
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Feed -->
    <?php if ($pagina['mostradas'] === 0): ?>
        <div class="card mm-card">
            <div class="card-body text-center text-muted py-5">
                Todavía no hay publicaciones. ¡Sé el primero en compartir algo! 📝
            </div>
        </div>
    <?php endif; ?>

    <div id="postsFeed"><?= $pagina['html'] ?></div>

    <div class="text-center mt-3 <?= $hayMas ? '' : 'd-none' ?>" id="postsMoreWrap">
        <button type="button" class="btn btn-outline-primary px-4 js-posts-more"
                data-feed-url="<?= base_url('modules/posts/feed.php') ?>"
                data-offset="<?= (int) $pagina['mostradas'] ?>">
            🔄 Ver más publicaciones
        </button>
    </div>
</div>

<!-- Modal: quiénes dieron "me gusta" -->
<div class="modal fade" id="likersModal" tabindex="-1" aria-labelledby="likersModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header mm-modal-header">
                <h5 class="modal-title" id="likersModalTitle">❤️ A quién le gusta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
            </div>
            <div class="modal-body js-likers-body">
                <p class="text-muted small mb-0">Cargando…</p>
            </div>
        </div>
    </div>
</div>

<script>
window.MM_POSTS_CONFIG = {
    likeUrl:          '<?= base_url('modules/posts/like.php') ?>',
    commentLikeUrl:   '<?= base_url('modules/posts/like_comment.php') ?>',
    likersUrl:        '<?= base_url('modules/posts/likers.php') ?>',
    commentUrl:       '<?= base_url('modules/posts/comment.php') ?>',
    getCommentsUrl:   '<?= base_url('modules/posts/get_comments.php') ?>',
    deleteCommentUrl: '<?= base_url('modules/posts/delete_comment.php') ?>',
    csrfToken:        '<?= e(csrf_token()) ?>',
    currentUserId:    <?= $userId ?>,
    isAdmin:          <?= $esAdmin ? 'true' : 'false' ?>
};
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
