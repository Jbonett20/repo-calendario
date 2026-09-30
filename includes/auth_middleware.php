<?php
/**
 * =====================================================
 *  monchomania - Middleware de autenticación
 *  Incluir al inicio de TODA página interna.
 *  - Exige sesión activa.
 *  - Si el usuario está inactivo (estado 2), cierra la sesión.
 * =====================================================
 */
require_once __DIR__ . '/../config/app.php';

$user = current_user();

// Sin sesión activa -> login
if ($user === null) {
    flash('error', 'Debes iniciar sesión para acceder a esta sección.');
    redirect('modules/auth/login.php');
}

// Usuario inactivo -> destruir sesión de forma segura y redirigir
if ((int) $user['estado'] !== 1) {
    $_SESSION = [];
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
    session_start();
    flash('error', 'Tu cuenta está inactiva. Contacta al administrador.');
    redirect('modules/auth/login.php');
}
