<?php
/**
 * =====================================================
 *  monchomania - Guardar un evento / reunión (solo superadmin)
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

$titulo      = limpiar_texto($_POST['titulo'] ?? '', 150);
$fecha       = trim((string) ($_POST['fecha'] ?? ''));
$hora        = trim((string) ($_POST['hora'] ?? ''));
$lugar       = limpiar_texto($_POST['lugar'] ?? '', 150);
$descripcion = limpiar_texto($_POST['descripcion'] ?? '', 500);

$errores = [];
if ($titulo === '') {
    $errores[] = 'El título es obligatorio.';
}

$fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
    $errores[] = 'La fecha no es válida.';
}
if ($hora !== '' && !preg_match('/^\d{2}:\d{2}$/', $hora)) {
    $errores[] = 'La hora no es válida.';
}

if ($errores) {
    flash('error', implode(' ', $errores));
    redirect('modules/events/index.php');
}

$stmt = Database::connection()->prepare(
    'INSERT INTO eventos (titulo, descripcion, fecha, hora, lugar, id_autor)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $titulo,
    $descripcion !== '' ? $descripcion : null,
    $fecha,
    $hora !== '' ? $hora : null,
    $lugar !== '' ? $lugar : null,
    $user['id'],
]);

notify_all(
    'evento',
    'Nuevo evento: ' . $titulo . ' (' . $fechaObj->format('d/m/Y') . ')',
    base_url('modules/calendar/index.php?fecha=' . $fecha),
    (int) $user['id']
);

flash('success', 'Evento creado correctamente.');
redirect('modules/events/index.php');
