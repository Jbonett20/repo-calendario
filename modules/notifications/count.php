<?php
require_once __DIR__ . '/../../includes/auth_middleware.php';

json_response([
    'success' => true,
    'sinLeer' => notifications_unread_count((int) $user['id']),
]);
