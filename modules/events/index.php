<?php
/**
 * =====================================================
 *  monchomania - Eventos y Reuniones (solo superadmin)
 *  Los eventos creados aquí aparecen en el calendario.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';

require_admin();
ensure_app_tables();

$eventos = Database::connection()->query(
    'SELECT e.*, u.nombre, u.apellidos
       FROM eventos e
       JOIN usuarios u ON u.id = e.id_autor
      ORDER BY e.fecha DESC, e.hora ASC'
)->fetchAll();

$hoy = date('Y-m-d');

$pageTitle = 'Eventos y Reuniones';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">📌 Eventos y Reuniones</h1>
            <p class="text-muted mb-0">Crea las reuniones de la comunidad: aparecerán en el calendario.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#newEventForm" aria-expanded="false">
            ➕ Crear evento
        </button>
    </div>

    <?php if (flash_has('error')): ?>
        <div class="alert alert-danger"><?= e(flash('error')) ?></div>
    <?php endif; ?>
    <?php if (flash_has('success')): ?>
        <div class="alert alert-success" data-auto-dismiss><?= e(flash('success')) ?></div>
    <?php endif; ?>

    <div class="collapse mb-4" id="newEventForm">
        <div class="card mm-card shadow-sm">
            <div class="card-header mm-card-header">Nuevo evento</div>
            <div class="card-body">
                <form method="post" action="<?= base_url('modules/events/save.php') ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="titulo">Título *</label>
                            <input type="text" id="titulo" name="titulo" class="form-control" required
                                   placeholder="Reunión de la comunidad">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="fecha">Fecha *</label>
                            <input type="date" id="fecha" name="fecha" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="hora">Hora</label>
                            <input type="time" id="hora" name="hora" class="form-control">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="lugar">Lugar</label>
                            <input type="text" id="lugar" name="lugar" class="form-control"
                                   placeholder="Salón comunal, casa de…">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="descripcion">Descripción</label>
                            <textarea id="descripcion" name="descripcion" rows="2" class="form-control"></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">Guardar evento</button>
                </form>
            </div>
        </div>
    </div>

    <?php if (!$eventos): ?>
        <div class="text-center py-5">
            <div class="mm-feature-icon mx-auto mb-3">📭</div>
            <p class="text-muted">Todavía no hay eventos creados.</p>
        </div>
    <?php else: ?>
        <div class="mm-news-list">
            <?php foreach ($eventos as $ev): ?>
                <?php $pasado = $ev['fecha'] < $hoy; ?>
                <div class="mm-news-item mm-event-item <?= $pasado ? 'mm-event-past' : '' ?>">
                    <span class="mm-news-item-media">
                        <span class="mm-event-date">
                            <span class="mm-event-day"><?= e(date('d', strtotime($ev['fecha']))) ?></span>
                            <span class="mm-event-month"><?= e(mes_corto_es((int) date('n', strtotime($ev['fecha'])))) ?></span>
                        </span>
                    </span>
                    <span class="mm-news-item-body">
                        <span class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                            <span class="badge mm-news-type-badge">
                                <?= $pasado ? '✅ Realizado' : '📌 Programado' ?>
                            </span>
                            <span class="text-muted small">
                                <time datetime="<?= e($ev['fecha']) ?>"><?= e(date('d/m/Y', strtotime($ev['fecha']))) ?></time>
                                <?php if ($ev['hora']): ?>
                                    ·
                                    <time datetime="<?= e(substr($ev['hora'], 0, 5)) ?>">
                                        <?= e(substr($ev['hora'], 0, 5)) ?>
                                    </time>
                                <?php endif; ?>
                            </span>
                        </span>
                        <span class="h6 mm-news-item-title d-block mb-1"><?= e($ev['titulo']) ?></span>
                        <?php if ($ev['lugar']): ?>
                            <span class="mm-news-item-preview text-muted d-block mb-1">📍 <?= e($ev['lugar']) ?></span>
                        <?php endif; ?>
                        <?php if (trim((string) $ev['descripcion']) !== ''): ?>
                            <span class="mm-news-item-preview text-muted d-block mb-2"><?= e($ev['descripcion']) ?></span>
                        <?php endif; ?>
                        <span class="mm-news-item-meta">
                            <span>Creado por <?= e($ev['nombre'] . ' ' . $ev['apellidos']) ?></span>
                        </span>
                    </span>
                    <span class="ms-auto">
                        <form method="post" action="<?= base_url('modules/events/delete.php') ?>"
                              onsubmit="return confirm('¿Eliminar este evento?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar evento">🗑️</button>
                        </form>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
