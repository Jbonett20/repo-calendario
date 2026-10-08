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

// Codificación UTF-8 en todo el flujo (acentos, ñ, emojis)
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
ini_set('default_charset', 'UTF-8');

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

/**
 * URL de un recurso estático con versión (fecha de modificación).
 * Evita que el navegador use una copia antigua tras actualizar el CSS/JS.
 */
function asset_versioned(string $path): string
{
    $file = dirname(__DIR__) . '/assets/' . ltrim($path, '/');
    return asset($path) . '?v=' . (is_file($file) ? filemtime($file) : '1');
}

/** URL de un archivo subido en /uploads. */
function uploads_url(string $path = ''): string
{
    return base_url('uploads/' . ltrim($path, '/'));
}

/**
 * Fecha y hora en formato 12 h (am/pm) según el servidor.
 * El navegador la reemplaza por la hora local del usuario (ver main.js).
 */
function fecha_hora_local(?string $fechaSql): string
{
    if ($fechaSql === null || $fechaSql === '') {
        return '';
    }
    // Las fechas se guardan en UTC; el texto de respaldo usa la zona del servidor.
    $ts = strtotime($fechaSql . ' UTC');
    if (!$ts) {
        return '';
    }
    $sufijo = (int) date('G', $ts) < 12 ? 'a. m.' : 'p. m.';
    return date('d/m/Y g:i', $ts) . ' ' . $sufijo;
}

/**
 * Fecha/hora en ISO-8601 UTC para el atributo datetime de <time>.
 * main.js lo convierte a la hora local del dispositivo del usuario.
 */
function fecha_iso(?string $fechaSql): string
{
    if ($fechaSql === null || $fechaSql === '') {
        return '';
    }
    $ts = strtotime($fechaSql . ' UTC');
    return $ts ? gmdate('Y-m-d\TH:i:s\Z', $ts) : '';
}

/** Mes abreviado en español (ene, feb, mar…). */
function mes_corto_es(int $mes): string
{
    $meses = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
    return $meses[$mes - 1] ?? '';
}

/**
 * Limpia un texto escrito por el usuario para que se guarde y se muestre bien:
 * - corrige secuencias UTF-8 inválidas (así la ñ y las tildes nunca salen mal),
 * - quita caracteres de control raros,
 * - recorta espacios y limita la longitud.
 */
function limpiar_texto(?string $texto, int $maxLargo = 255): string
{
    $texto = (string) $texto;

    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'UTF-8');
    }

    // Caracteres de control (se conservan los saltos de línea y las tabulaciones)
    $limpio = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto);
    $texto  = trim(strip_tags($limpio ?? $texto));

    if (mb_strlen($texto) > $maxLargo) {
        $texto = mb_substr($texto, 0, $maxLargo);
    }

    return $texto;
}

/** Cantidad de usuarios activos de la comunidad. */
function total_users_count(): int
{
    try {
        return (int) Database::connection()
            ->query('SELECT COUNT(*) FROM usuarios WHERE estado = 1')
            ->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
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
    // JSON_UNESCAPED_UNICODE: envía acentos y emojis tal cual (sin \uXXXX)
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* =====================================================
 *  SUBIDA Y BORRADO DE ARCHIVOS (PERFIL / NOTICIAS)
 * ===================================================== */

/** Mensaje legible para los códigos de error de $_FILES['error']. */
function upload_error_message(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'El archivo es demasiado grande para el servidor (máximo permitido: '
                . ini_get('upload_max_filesize') . ').';
        case UPLOAD_ERR_PARTIAL:
            return 'La subida del archivo se interrumpió. Inténtalo de nuevo.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'El servidor no tiene configurada una carpeta temporal para subidas.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'No se pudo escribir el archivo en el disco del servidor.';
        case UPLOAD_ERR_EXTENSION:
            return 'Una extensión de PHP detuvo la subida del archivo.';
        default:
            return 'Error al subir el archivo (código ' . $code . ').';
    }
}

/**
 * Valida y guarda un archivo subido en /uploads/{$subdir}.
 * Devuelve el nombre del archivo guardado.
 *
 * @param array<string,mixed>  $file       Entrada de $_FILES
 * @param array<string,string> $extensions MIME permitido => extensión del archivo
 * @throws RuntimeException
 */
function store_uploaded_file(
    array $file,
    string $subdir,
    array $extensions,
    int $maxBytes,
    string $formatError,
    string $sizeError
): string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message((int) $file['error']));
    }

    // Validar el tipo real del archivo (no la extensión ni el MIME que envía el navegador)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!isset($extensions[$mime])) {
        throw new RuntimeException($formatError);
    }
    if ((int) $file['size'] > $maxBytes) {
        throw new RuntimeException($sizeError);
    }

    $filename = $subdir . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];

    $dir = dirname(__DIR__) . '/uploads/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
    }

    return $filename;
}

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

    return store_uploaded_file(
        $file,
        $subdir,
        [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ],
        2 * 1024 * 1024,
        'Formato de imagen no permitido (usa JPG, PNG, WEBP o GIF).',
        'La imagen no puede superar los 2 MB.'
    );
}

/** Tipos MIME aceptados para los videos y audios de Noticias (MIME => extensión). */
function news_media_extensions(string $tipo): array
{
    if ($tipo === 'video') {
        return [
            'video/mp4'       => 'mp4',
            'video/webm'      => 'webm',
            'video/ogg'       => 'ogv',
            'video/quicktime' => 'mov',
        ];
    }

    return [
        'audio/mpeg'  => 'mp3',
        'audio/mp4'   => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/wav'   => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg'   => 'ogg',
        'audio/webm'  => 'weba',
    ];
}

/** Convierte un valor de php.ini (2M, 512K, 1G) a megabytes. */
function ini_size_to_mb(string $valor): int
{
    $valor  = trim($valor);
    $numero = (int) $valor;
    $sufijo = strtolower(substr($valor, -1));

    if ($sufijo === 'g') {
        return $numero * 1024;
    }
    if ($sufijo === 'm') {
        return $numero;
    }
    if ($sufijo === 'k') {
        return max(1, (int) round($numero / 1024));
    }
    return max(1, (int) round($numero / 1048576));
}

/** Límite real de subida (en MB) impuesto por la configuración del servidor. */
function upload_limit_mb(): int
{
    return max(1, min(
        ini_size_to_mb((string) ini_get('upload_max_filesize')),
        ini_size_to_mb((string) ini_get('post_max_size'))
    ));
}

/** Tamaño máximo permitido (en MB) para los medios de Noticias. */
function news_media_max_mb(string $tipo): int
{
    $maximoApp = $tipo === 'video' ? 100 : 25;
    return max(1, min($maximoApp, upload_limit_mb()));
}

/**
 * Valida y guarda un video o audio de Noticias en /uploads/news.
 * Devuelve el nombre del archivo guardado.
 * @throws RuntimeException
 */
function handle_media_upload(array $file, string $tipo, string $subdir = 'news'): string
{
    $mb       = news_media_max_mb($tipo);
    $formatos = $tipo === 'video' ? 'MP4, WEBM, OGV o MOV' : 'MP3, M4A, WAV, OGG o WEBM';
    $articulo = $tipo === 'video' ? 'El video' : 'El audio';

    return store_uploaded_file(
        $file,
        $subdir,
        news_media_extensions($tipo),
        $mb * 1024 * 1024,
        'Formato de ' . $tipo . ' no permitido (usa ' . $formatos . ').',
        $articulo . ' no puede superar los ' . $mb . ' MB.'
    );
}

/**
 * Crea las tablas opcionales del proyecto si todavía no existen.
 * El proyecto no usa migraciones, así cada módulo funciona sin pasos manuales.
 */
function ensure_app_tables(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $tablas = [
        'noticias_likes' => 'CREATE TABLE IF NOT EXISTS `noticias_likes` (
                `id_noticia`     INT UNSIGNED NOT NULL,
                `id_usuario`     INT UNSIGNED NOT NULL,
                `fecha_creacion` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id_noticia`, `id_usuario`),
                KEY `idx_nl_usuario` (`id_usuario`),
                CONSTRAINT `fk_nl_noticia` FOREIGN KEY (`id_noticia`)
                  REFERENCES `noticias` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT `fk_nl_usuario` FOREIGN KEY (`id_usuario`)
                  REFERENCES `usuarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'eventos' => 'CREATE TABLE IF NOT EXISTS `eventos` (
                `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `titulo`         VARCHAR(150) NOT NULL,
                `descripcion`    TEXT         DEFAULT NULL,
                `fecha`          DATE         NOT NULL,
                `hora`           TIME         DEFAULT NULL,
                `lugar`          VARCHAR(150) DEFAULT NULL,
                `id_autor`       INT UNSIGNED NOT NULL,
                `fecha_creacion` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_eventos_fecha` (`fecha`),
                CONSTRAINT `fk_eventos_autor` FOREIGN KEY (`id_autor`)
                  REFERENCES `usuarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'notificaciones' => 'CREATE TABLE IF NOT EXISTS `notificaciones` (
                `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_usuario`     INT UNSIGNED NOT NULL,
                `tipo`           VARCHAR(30)  NOT NULL DEFAULT \'general\',
                `mensaje`        VARCHAR(255) NOT NULL,
                `url`            VARCHAR(255) DEFAULT NULL,
                `leida`          TINYINT(1)   NOT NULL DEFAULT 0,
                `fecha_creacion` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_notif_usuario` (`id_usuario`, `leida`),
                CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`id_usuario`)
                  REFERENCES `usuarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ];

    try {
        $pdo       = Database::connection();
        $existentes = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tablas as $nombre => $sql) {
            if (!in_array($nombre, $existentes, true)) {
                $pdo->exec($sql);
            }
        }
    } catch (Throwable $e) {
        // Sin permisos de creación en la BD: las tablas deben existir (ver schema.sql).
    }
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
