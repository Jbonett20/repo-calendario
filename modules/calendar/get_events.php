<?php
/**
 * =====================================================
 *  monchomania - API: eventos del calendario (JSON)
 *  Los eventos los crea el superadmin y los ve toda la comunidad.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';

ensure_app_tables();

$stmt = Database::connection()->query(
    'SELECT id, titulo, descripcion, fecha, hora, lugar
       FROM eventos
      ORDER BY fecha ASC, hora ASC'
);

$result = [];
foreach ($stmt->fetchAll() as $e) {
    $partes = explode('-', (string) $e['fecha']);
    $result[] = [
        'id'          => (int) $e['id'],
        'titulo'      => $e['titulo'],
        'descripcion' => $e['descripcion'],
        'fecha'       => $e['fecha'],
        'hora'        => $e['hora'] ? substr((string) $e['hora'], 0, 5) : null,
        'lugar'       => $e['lugar'],
        'dia'         => (int) ($partes[2] ?? 0),
        'mes'         => (int) ($partes[1] ?? 0),
        'anio'        => (int) ($partes[0] ?? 0),
    ];
}

json_response($result);
