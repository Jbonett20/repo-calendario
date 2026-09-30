<?php
declare(strict_types=1);

/**
 * =====================================================
 *  monchomania - Bootstrap de la aplicación
 *  Carga Composer/.env, inicia sesión y define helpers.
 * =====================================================
 */

$autoloadPath = __DIR__ . '/../vendor/autoload.php';

// Cargar dependencias de Composer (phpdotenv) si están instaladas
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
} else {
    // Fallback sin Composer: lee el .env de forma manual
    load_env_fallback(__DIR__ . '/../.env');
}

/**
 * Lee un archivo .env simple (KEY=VALUE) cuando Composer no está instalado.
 */
function load_env_fallback(string $file): void
{
    if (!file_exists($file)) {
        return;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Quitar comillas simples o dobles
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

// Zona horaria
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Bogota');

// Manejo de errores según el entorno
if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Inicio de sesión seguro
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

/* =====================================================
 *  HELPERS GENERALES
 * ===================================================== */

/** Escapa texto para salida HTML segura. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Devuelve la URL base absoluta del proyecto. */
function base_url(string $path = ''): string
{
    $base = rtrim($_ENV['APP_URL'] ?? '', '/');
    return $base . '/' . ltrim($path, '/');
}

/** URL de un recurso estático en /assets. */
function asset(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

/** URL de un archivo subido en /uploads. */
function uploads_url(string $path = ''): string
{
    return base_url('uploads/' . ltrim($path, '/'));
}

/** Redirige y detiene la ejecución. */
function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

/** ¿Hay una sesión iniciada? */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/** Devuelve el usuario logueado (con su rol) o null. */
function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $stmt = Database::connection()->prepare(
        'SELECT u.*, r.nombre_rol
           FROM usuarios u
           JOIN roles r ON r.id = u.id_rol
          WHERE u.id = ? LIMIT 1'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** ¿El usuario actual es Superadministrador (rol 1)? */
function is_admin(): bool
{
    $user = current_user();
    return $user !== null && (int) $user['id_rol'] === 1;
}

/**
 * Mensajes flash (solo viven una petición).
 * flash('clave', 'mensaje') => guarda.  flash('clave') => recupera y borra.
 */
function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

/** Comprueba si existe un mensaje flash. */
function flash_has(string $key): bool
{
    return isset($_SESSION['flash'][$key]);
}

/* =====================================================
 *  CSRF (protección contra falsificación de peticiones)
 * ===================================================== */

/** Devuelve (o crea) el token CSRF de la sesión. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo oculto <input> para incluir en formularios. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verifica el token CSRF.
 * Acepta el token por POST (formularios) o por cabecera X-CSRF-Token (fetch/JSON).
 */
function verify_csrf_request(): bool
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && $token !== ''
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* =====================================================
 *  VISIBILIDAD DE MÓDULOS
 * ===================================================== */

/**
 * Indica si un módulo (por su clave) está visible para el usuario actual.
 * El superadmin siempre ve todos los módulos.
 */
function module_is_visible(string $clave): bool
{
    if (!is_logged_in()) {
        return false;
    }
    if (is_admin()) {
        return true;
    }
    $user = current_user();
    $stmt = Database::connection()->prepare(
        'SELECT pm.visible
           FROM permisos_modulos pm
           JOIN modulos m ON m.id = pm.id_modulo
          WHERE pm.id_rol = ? AND m.clave_modulo = ? LIMIT 1'
    );
    $stmt->execute([$user['id_rol'], $clave]);
    $row = $stmt->fetch();
    return $row ? (bool) (int) $row['visible'] : false;
}

/** Barrera para páginas exclusivas del superadmin. */
function require_admin(): void
{
    if (!is_admin()) {
        flash('error', 'No tienes permisos para acceder a esta sección.');
        redirect('index.php');
    }
}

/* =====================================================
 *  RESPUESTAS JSON (para peticiones fetch/AJAX)
 * ===================================================== */

/** Emite una respuesta JSON y detiene el script. */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/* =====================================================
 *  SUBIDA Y BORRADO DE IMÁGENES DE PERFIL / NOTICIAS
 * ===================================================== */

/**
 * Valida y mueve una imagen subida a /uploads/{$subdir}.
 * Devuelve el nombre del archivo guardado.
 * @throws RuntimeException
 */
function handle_photo_upload(array $file, string $subdir = 'profiles'): string
{
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error al subir el archivo (código ' . $file['error'] . ').');
    }

    // Validar tipo real del archivo
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Formato de imagen no permitido (usa JPG, PNG, WEBP o GIF).');
    }

    // Límite de 2 MB
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('La imagen no puede superar los 2 MB.');
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $filename = $subdir . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];

    $root   = dirname(__DIR__);
    $dir    = $root . '/uploads/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $target = $dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('No se pudo guardar la imagen en el servidor.');
    }

    return $filename;
}

/**
 * Elimina una imagen del servidor (unlink) para ahorrar espacio en disco.
 */
function delete_photo(?string $filename, string $subdir = 'profiles'): void
{
    if ($filename === null || $filename === '') {
        return;
    }
    $path = dirname(__DIR__) . '/uploads/' . $subdir . '/' . $filename;
    if (is_file($path)) {
        @unlink($path);
    }
}
