<?php
/**
 * =====================================================
 *  monchomania - Eliminar un evento / reunión (solo superadmin)
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';

require_admin();
ensure_app_tables();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/events/index.php');
}
if (!verify_csrf_request()) {
    flash('error', 'Token de seguridad inválido.');
    redirect('modules/events/index.php');
}

$eventoId = (int) ($_POST['id'] ?? 0);
if ($eventoId <= 0) {
    flash('error', 'Evento no válido.');
    redirect('modules/events/index.php');
}

Database::connection()
    ->prepare('DELETE FROM eventos WHERE id = ?')
    ->execute([$eventoId]);

flash('success', 'Evento eliminado correctamente.');
redirect('modules/events/index.php');
