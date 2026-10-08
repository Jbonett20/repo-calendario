<?php
/**
 * Cabecera HTML común de todas las páginas.
 */
require_once __DIR__ . '/../config/app.php';

$pageTitle    = $pageTitle ?? 'monchomania';
$currentUser  = is_logged_in() ? current_user() : null;

// Declarar UTF-8 también a nivel HTTP
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#012C94">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> · monchomania</title>    <link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset('img/icon-192.png') ?>">
    <link rel="manifest" href="<?= base_url('manifest.json') ?>">

    <!-- App instalable (PWA) -->
    <meta name="theme-color" content="#012C94">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="monchomania">

    <!-- Tipografías -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Estilos propios -->
    <link href="<?= asset_versioned('css/style.css') ?>" rel="stylesheet">
    <link rel="manifest" href="manifest.json">
</head>
<body>
