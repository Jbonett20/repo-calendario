<?php
/**
 * =====================================================
 *  monchomania - Vista pública de perfil
 *  Muestra el nombre, el cumpleaños y los saludos que ha
 *  recibido una persona de la comunidad.
 *  El autor de un saludo (o un admin) puede editarlo o
 *  eliminarlo.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../calendar/helpers.php';

$pdo      = Database::connection();
$personaId = (int) ($_GET['id'] ?? 0);

if ($personaId <= 0) {
    flash('error', 'Usuario no encontrado.');
    redirect('modules/calendar/index.php');
}

$stmt = $pdo->prepare(
    'SELECT id, usuario, nombre, apellidos, fecha_nacimiento, direccion, barrio, zona, foto
       FROM usuarios
      WHERE id = ? AND estado = 1
      LIMIT 1'
);
$stmt->execute([$personaId]);
$persona = $stmt->fetch();

if (!$persona) {
    flash('error', 'Usuario no encontrado o inactivo.');
    redirect('modules/calendar/index.php');
}

$currentUserId = (int) $user['id'];
$esAdmin       = is_admin();

// ---- Enviar un saludo (formulario clásico con POST + redirección) ----
$errorEnvio = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_request()) {
        $errorEnvio = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $comentario = limpiar_texto($_POST['comentario'] ?? '', 500);
        if ($comentario === '') {
            $errorEnvio = 'Escribe un saludo antes de enviarlo.';
        } else {
            $pdo->prepare(
                'INSERT INTO cumple_comentarios (id_usuario_destino, id_usuario_autor, comentario)
                 VALUES (?, ?, ?)'
            )->execute([$personaId, $currentUserId, $comentario]);

            if ($personaId !== $currentUserId) {
                notify_user(
                    $personaId,
                    'saludo',
                    trim($user['nombre'] . ' ' . $user['apellidos']) . ' te envió un saludo de cumpleaños 🎂',
                    base_url('modules/profile/view.php?id=' . $personaId)
                );
            }

            flash('success', '¡Saludo enviado! 🎉');
            redirect('modules/profile/view.php?id=' . $personaId);
        }
    }
}

$saludos     = cumple_comments($personaId);
$proximo     = cumple_proximo((string) $persona['fecha_nacimiento']);
$fotoUrl     = $persona['foto']
    ? uploads_url('profiles/' . $persona['foto'])
    : asset('img/avatar-default.svg');
$nombreCompleto = trim($persona['nombre'] . ' ' . $persona['apellidos']);

$pageTitle = $nombreCompleto;
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('modules/calendar/index.php') ?>">← Volver al calendario</a>
        <?php if ($personaId === $currentUserId): ?>
            <a class="btn btn-outline-primary btn-sm" href="<?= base_url('modules/profile/index.php') ?>">✏️ Editar mi perfil</a>
        <?php endif; ?>
    </div>

    <?php if (flash_has('error')): ?>
        <div class="alert alert-danger" data-auto-dismiss><?= e(flash('error')) ?></div>
    <?php endif; ?>
    <?php if (flash_has('success')): ?>
        <div class="alert alert-success" data-auto-dismiss><?= e(flash('success')) ?></div>
    <?php endif; ?>
    <?php if ($errorEnvio !== null): ?>
        <div class="alert alert-danger"><?= e($errorEnvio) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Columna: ficha de la persona -->
        <div class="col-lg-4">
            <div class="card mm-card shadow-sm text-center p-4">
                <img src="<?= e($fotoUrl) ?>" alt="Foto de <?= e($nombreCompleto) ?>" class="mm-avatar-lg mx-auto mb-3">
                <h1 class="h5 mb-1"><?= e($nombreCompleto) ?></h1>
                <p class="text-muted mb-3">@<?= e($persona['usuario']) ?></p>

                <span class="badge mm-zone mb-3"><?= e($persona['zona']) ?></span>

                <ul class="list-unstyled text-start small text-muted mb-0">
                    <li class="mb-1">🎂 Nació el <?= e(date('d/m/Y', strtotime($persona['fecha_nacimiento']))) ?></li>
                    <?php if ($proximo): ?>
                        <li class="mb-1">
                            🎉 Próximo cumpleaños: <strong><?= e($proximo['fecha']->format('d/m/Y')) ?></strong>
                            <?php if ($proximo['es_hoy']): ?>
                                · ¡Hoy está de cumpleaños! 🥳
                            <?php else: ?>
                                · faltan <?= (int) $proximo['dias'] ?> días
                            <?php endif; ?>
                        </li>
                        <li class="mb-1">🎈 Cumplirá <?= (int) $proximo['edad'] ?> años</li>
                    <?php endif; ?>
                    <?php if ($persona['direccion']): ?><li class="mb-1">📍 <?= e($persona['direccion']) ?></li><?php endif; ?>
                    <?php if ($persona['barrio']): ?><li class="mb-1">🏘️ <?= e($persona['barrio']) ?></li><?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Columna: saludos -->
        <div class="col-lg-8">
            <div class="card mm-card shadow-sm">
                <div class="card-header mm-card-header">
                    💬 Saludos para <?= e($persona['nombre']) ?>
                    (<span class="js-comments-count"><?= count($saludos) ?></span>)
                </div>
                <div class="card-body">
                    <form method="post" action="<?= e(base_url('modules/profile/view.php?id=' . $personaId)) ?>" class="mb-4">
                        <?= csrf_field() ?>
                        <label class="form-label small text-muted" for="comentario">
                            <?= $personaId === $currentUserId ? 'Déjate un mensaje o deséate un feliz cumpleaños 🎂' : 'Escríbele un saludo 🎉' ?>
                        </label>
                        <div class="d-flex gap-2 align-items-start">
                            <textarea id="comentario" name="comentario" rows="1" maxlength="500"
                                      class="form-control" placeholder="Escribe un saludo…" data-emoji required></textarea>
                            <button type="submit" class="btn btn-primary px-3">Enviar</button>
                        </div>
                    </form>

                    <div class="js-comments-list mm-comments">
                        <?php if (!$saludos): ?>
                            <p class="text-muted small mb-0">Aún no hay saludos. ¡Sé el primero en felicitar! 🎉</p>
                        <?php else: ?>
                            <?php foreach ($saludos as $s): ?>
                                <div class="mm-comment">
                                    <img src="<?= e($s['foto_url']) ?>" alt="">
                                    <div class="flex-grow-1">
                                        <div class="bubble"><?= e($s['comentario']) ?></div>
                                        <div class="small text-muted mt-1 d-flex align-items-center gap-2 flex-wrap">
                                            <span>
                                                <?= e($s['autor']) ?> ·
                                                <time datetime="<?= e(fecha_iso($s['fecha_creacion'])) ?>">
                                                    <?= e(fecha_hora_local($s['fecha_creacion'])) ?>
                                                </time>
                                            </span>
                                            <?= cumple_comment_actions_html($s, $currentUserId, $esAdmin) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.MM_COMMENT_DELETE = {
    url:     '<?= base_url('modules/calendar/delete_comment.php') ?>',
    isAdmin: <?= $esAdmin ? 'true' : 'false' ?>
};
window.MM_COMMENT_EDIT = {
    url:           '<?= base_url('modules/calendar/edit_comment.php') ?>',
    isAdmin:       <?= $esAdmin ? 'true' : 'false' ?>,
    currentUserId: <?= $currentUserId ?>
};
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
