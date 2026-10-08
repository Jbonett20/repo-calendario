<?php
/**
 * =====================================================
 *  monchomania - Módulo Noticias (listado compacto)
 *  - El superadmin crea publicaciones (imagen, video, audio, frase).
 *  - Todos los usuarios logueados ven la lista y entran al detalle
 *    (detail.php) para ver el medio completo, los "me gusta" y los comentarios.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/helpers.php';

// Visibilidad del módulo según configuración (solo aplica a usuarios normales)
if (!is_admin() && !module_is_visible('noticias')) {
    flash('error', 'El módulo de Noticias no está disponible actualmente.');
    redirect('modules/calendar/index.php');
}

ensure_app_tables();

$isAdmin = is_admin();

// Lista de publicaciones con el conteo de comentarios y "me gusta"
$stmt = Database::connection()->query(
    "SELECT n.*, u.nombre, u.apellidos, u.foto AS autor_foto,
            (SELECT COUNT(*) FROM noticias_comentarios c WHERE c.id_noticia = n.id) AS total_comentarios,
            (SELECT COUNT(*) FROM noticias_likes l WHERE l.id_noticia = n.id)       AS total_likes
       FROM noticias n
       JOIN usuarios u ON u.id = n.id_autor
      ORDER BY n.fecha_creacion DESC"
);
$noticias = $stmt->fetchAll();

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
                                <div class="form-text">JPG, PNG, WEBP o GIF · máx. 2 MB</div>
                            </div>

                            <div class="col-12 news-field d-none" data-type="video">
                                <label class="form-label d-block">Video *</label>
                                <div class="btn-group flex-wrap mb-2" role="group" aria-label="Origen del video">
                                    <input type="radio" class="btn-check" name="video_origen" id="videoOrigenSubir"
                                           value="subir" checked onchange="toggleVideoSource('subir')">
                                    <label class="btn btn-outline-primary" for="videoOrigenSubir">⬆️ Subir desde mi equipo</label>

                                    <input type="radio" class="btn-check" name="video_origen" id="videoOrigenEnlace"
                                           value="enlace" onchange="toggleVideoSource('enlace')">
                                    <label class="btn btn-outline-primary" for="videoOrigenEnlace">🔗 Compartir enlace</label>
                                </div>

                                <div class="js-video-source" id="videoSourceSubir">
                                    <input type="file" id="video_file" name="video_file"
                                           accept="video/mp4,video/webm,video/ogg,video/quicktime" class="form-control">
                                    <div class="form-text">
                                        MP4, WEBM, OGV o MOV · máx. <?= news_media_max_mb('video') ?> MB ·
                                        se reproduce en la página sin opción de descarga
                                    </div>
                                </div>

                                <div class="js-video-source d-none" id="videoSourceEnlace">
                                    <input type="url" id="url_video" name="url_video" class="form-control"
                                           placeholder="https://www.youtube.com/watch?v=…">
                                    <div class="form-text">
                                        Enlaces de YouTube o Vimeo (se incrustan y no se pueden descargar)
                                        o archivo directo .mp4 / .webm
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 news-field d-none" data-type="cancion">
                                <label class="form-label" for="audio_file">Archivo de audio *</label>
                                <input type="file" id="audio_file" name="audio_file"
                                       accept="audio/mpeg,audio/mp4,audio/wav,audio/ogg,audio/webm,.mp3,.m4a,.wav,.ogg"
                                       class="form-control">
                                <div class="form-text">
                                    MP3, M4A, WAV, OGG o WEBM · máx. <?= news_media_max_mb('audio') ?> MB ·
                                    se reproduce en la página sin opción de descarga
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">Publicar</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Listado compacto de publicaciones -->
    <?php if (!$noticias): ?>
        <div class="text-center py-5">
            <div class="mm-feature-icon mx-auto mb-3">📭</div>
            <p class="text-muted">Todavía no hay publicaciones.</p>
        </div>
    <?php else: ?>
        <div class="mm-news-list">
            <?php foreach ($noticias as $n): ?>
                <?php
                    $autorFoto = $n['autor_foto']
                        ? uploads_url('profiles/' . $n['autor_foto'])
                        : asset('img/avatar-default.svg');
                    $detalleUrl = base_url('modules/news/detail.php?id=' . (int) $n['id']);
                ?>
                <a class="mm-news-item" href="<?= $detalleUrl ?>">
                    <span class="mm-news-item-media">
                        <?php if ($n['tipo'] === 'imagen' && $n['url_media']): ?>
                            <img src="<?= e(news_media_src($n['url_media'])) ?>" class="mm-news-thumb"
                                 alt="<?= e($n['titulo']) ?>" loading="lazy">
                        <?php else: ?>
                            <span class="mm-news-icon"><?= news_type_icon($n['tipo']) ?></span>
                        <?php endif; ?>
                    </span>

                    <span class="mm-news-item-body">
                        <span class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                            <span class="badge mm-news-type-badge">
                                <?= news_type_icon($n['tipo']) ?> <?= e(news_type_label($n['tipo'])) ?>
                            </span>
                            <time class="text-muted small" datetime="<?= e(fecha_iso($n['fecha_creacion'])) ?>">
                                <?= e(fecha_hora_local($n['fecha_creacion'])) ?>
                            </time>
                        </span>
                        <span class="h6 mm-news-item-title d-block mb-1"><?= e($n['titulo']) ?></span>
                        <span class="mm-news-item-preview text-muted d-block mb-2"><?= e(news_summary($n)) ?></span>
                        <span class="mm-news-item-meta">
                            <img src="<?= e($autorFoto) ?>" class="mm-news-mini-avatar" alt="">
                            <span><?= e($n['nombre'] . ' ' . $n['apellidos']) ?></span>
                            <span class="ms-auto">💬 <?= (int) $n['total_comentarios'] ?> · ❤️ <?= (int) $n['total_likes'] ?></span>
                        </span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
