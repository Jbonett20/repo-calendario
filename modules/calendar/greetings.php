<?php
/**
 * =====================================================
 *  monchomania - Mis saludos de cumpleaños
 *  La persona que cumple años ve aquí todos los saludos
 *  que le han enviado.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';

$pdo  = Database::connection();
$stmt = $pdo->prepare(
    'SELECT c.id, c.comentario, c.fecha_creacion, u.nombre, u.apellidos, u.foto
       FROM cumple_comentarios c
       JOIN usuarios u ON u.id = c.id_usuario_autor
      WHERE c.id_usuario_destino = ?
      ORDER BY c.fecha_creacion DESC'
);
$stmt->execute([$user['id']]);
$saludos = $stmt->fetchAll();

$esAdmin = is_admin();

// Días que faltan para el próximo cumpleaños
$nacimiento = $user['fecha_nacimiento'] ?? null;
$proximoCumple = null;
if ($nacimiento) {
    $partes = explode('-', (string) $nacimiento);
    $hoy    = new DateTime('today');
    $proximoCumple = new DateTime($hoy->format('Y') . '-' . ($partes[1] ?? '01') . '-' . ($partes[2] ?? '01'));
    if ($proximoCumple < $hoy) {
        $proximoCumple->modify('+1 year');
    }
}
$diasFaltantes = $proximoCumple ? (int) (new DateTime('today'))->diff($proximoCumple)->days : null;

$pageTitle = 'Mis saludos de cumpleaños';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0">🎂 Mis saludos de cumpleaños</h1>
            <p class="text-muted mb-0">
                <?php if ($proximoCumple): ?>
                    Tu cumpleaños: <strong><?= e($proximoCumple->format('d/m/Y')) ?></strong>
                    <?php if ($diasFaltantes === 0): ?>
                        · ¡Hoy es tu día! 🎉
                    <?php else: ?>
                        · faltan <?= (int) $diasFaltantes ?> días
                    <?php endif; ?>
                <?php else: ?>
                    Aquí están los saludos que te ha enviado la comunidad.
                <?php endif; ?>
            </p>
        </div>
        <a class="btn btn-outline-primary" href="<?= base_url('modules/calendar/index.php') ?>">📅 Ir al calendario</a>
    </div>

    <div class="card mm-card">
        <div class="card-header mm-card-header">
            💬 Saludos recibidos (<span class="js-comments-count"><?= count($saludos) ?></span>)
        </div>
        <div class="card-body">
            <div class="js-comments-list mm-comments">
                <?php if (!$saludos): ?>
                    <p class="text-muted small mb-0">Todavía no has recibido saludos. 🎈</p>
                <?php else: ?>
                    <?php foreach ($saludos as $s): ?>
                        <?php
                            $foto = $s['foto']
                                ? uploads_url('profiles/' . $s['foto'])
                                : asset('img/avatar-default.svg');
                        ?>
                        <div class="mm-comment">
                            <img src="<?= e($foto) ?>" alt="">
                            <div class="flex-grow-1">
                                <div class="bubble"><?= e($s['comentario']) ?></div>
                                <div class="small text-muted mt-1 d-flex align-items-center gap-2">
                                    <span>
                                        <?= e(trim($s['nombre'] . ' ' . $s['apellidos'])) ?> ·
                                        <time datetime="<?= e(fecha_iso($s['fecha_creacion'])) ?>">
                                            <?= e(fecha_hora_local($s['fecha_creacion'])) ?>
                                        </time>
                                    </span>
                                    <?php if ($esAdmin): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger js-comment-delete"
                                                data-comment-id="<?= (int) $s['id'] ?>"
                                                data-delete-url="<?= base_url('modules/calendar/delete_comment.php') ?>"
                                                title="Eliminar saludo">🗑️</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
