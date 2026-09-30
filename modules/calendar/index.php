<?php
/**
 * =====================================================
 *  monchomania - Calendario de Cumpleaños (vista)
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';

$pageTitle = 'Calendario de Cumpleaños';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h1 class="h3 mb-0">🎂 Calendario de Cumpleaños</h1>
    </div>
    <p class="text-muted mb-4">Toca un día resaltado para ver quién cumple años y enviarle un saludo.</p>

    <div class="row g-4">
        <div class="col-lg-9">
            <div class="card mm-card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <button id="prevMonth" class="btn btn-outline-secondary btn-sm px-3" aria-label="Mes anterior">‹</button>
                        <h2 id="calendarTitle" class="h5 mb-0 text-center"></h2>
                        <button id="nextMonth" class="btn btn-outline-secondary btn-sm px-3" aria-label="Mes siguiente">›</button>
                    </div>

                    <div class="text-center mb-2">
                        <button id="todayBtn" class="btn btn-link btn-sm text-decoration-none">📍 Ir a hoy</button>
                    </div>

                    <div id="weekDays" class="mm-weekdays"></div>
                    <div id="calendarGrid" class="mm-calendar">
                        <div class="text-center text-muted py-4">Cargando calendario…</div>
                    </div>

                    <div class="mm-legend mt-3">
                        <span><span class="legend-dot legend-birthday"></span> Con cumpleaños</span>
                        <span class="ms-3"><span class="legend-dot legend-today"></span> Hoy</span>
                        <span class="ms-3"><span class="legend-dot legend-gold"></span> Varios cumpleaños</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card mm-card shadow-sm">
                <div class="card-header mm-card-header">🎉 Próximos cumpleaños</div>
                <div class="card-body" id="upcomingBirthdays">
                    <p class="text-muted small mb-0">Cargando…</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============ Modal de cumpleaños ============ -->
<div class="modal fade" id="birthdayModal" tabindex="-1" aria-labelledby="birthdayModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header mm-modal-header">
                <h5 class="modal-title" id="birthdayModalTitle">🎂 Cumpleaños</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="birthdayPeople" class="row g-3"></div>

                <hr id="commentsDivider" class="d-none">

                <div id="commentsSection" class="d-none">
                    <h6 class="mb-3">💬 Saludos para <span id="selectedPersonName"></span></h6>
                    <div id="commentsList" class="mm-comments mb-3"></div>

                    <form id="commentForm" class="d-flex gap-2 align-items-start">
                        <input type="hidden" id="commentUserId" name="usuario_id">
                        <textarea id="commentText" name="comentario" rows="1" maxlength="500"
                                  class="form-control" placeholder="Escribe un saludo…" required></textarea>
                        <button type="submit" class="btn btn-primary px-3">Enviar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.MM_CALENDAR_CONFIG = {
    getBirthdaysUrl: '<?= base_url('modules/calendar/get_birthdays.php') ?>',
    getCommentsUrl:  '<?= base_url('modules/calendar/get_comments.php') ?>',
    commentUrl:      '<?= base_url('modules/calendar/comment.php') ?>',
    csrfToken:       '<?= e(csrf_token()) ?>',
    currentUserId:   <?= (int) $user['id'] ?>
};
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
