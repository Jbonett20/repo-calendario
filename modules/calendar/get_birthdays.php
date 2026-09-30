<?php
/**
 * =====================================================
 *  monchomania - API: lista de cumpleaños (JSON)
 *  Devuelve los usuarios activos con su fecha de nacimiento.
 * =====================================================
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';

$stmt = Database::connection()->query(
    "SELECT id, nombre, apellidos, fecha_nacimiento, direccion, barrio, zona, foto
       FROM usuarios
      WHERE estado = 1
      ORDER BY MONTH(fecha_nacimiento), DAY(fecha_nacimiento)"
);
$usuarios = $stmt->fetchAll();

$result = [];
foreach ($usuarios as $u) {
    $fecha  = $u['fecha_nacimiento'];
    $parts  = explode('-', $fecha); // YYYY-MM-DD

    $result[] = [
        'id'               => (int) $u['id'],
        'nombre'           => $u['nombre'],
        'apellidos'        => $u['apellidos'],
        'nombre_completo'  => trim($u['nombre'] . ' ' . $u['apellidos']),
        'foto_url'         => $u['foto']
                                ? uploads_url('profiles/' . $u['foto'])
                                : asset('img/avatar-default.svg'),
        'direccion'        => $u['direccion'],
        'barrio'           => $u['barrio'],
        'zona'             => $u['zona'],
        'fecha_nacimiento' => $fecha,
        'dia'              => (int) ($parts[2] ?? 0),
        'mes'              => (int) ($parts[1] ?? 0),
    ];
}

json_response($result);
