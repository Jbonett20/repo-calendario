<?php
/**
 * =====================================================
 *  monchomania - Cerrar sesión (destruye la sesión)
 * =====================================================
 */
require_once __DIR__ . '/../../config/app.php';

// Vaciar variables de sesión
$_SESSION = [];

// Invalidar la cookie de sesión
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// Nueva sesión para mostrar el mensaje de despedida
session_start();
flash('success', 'Sesión cerrada correctamente. ¡Hasta pronto!');
redirect('modules/auth/login.php');
