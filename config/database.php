<?php
declare(strict_types=1);

/**
 * =====================================================
 *  monchomania - Conexión PDO a MySQL/MariaDB
 *  Patrón Singleton + prepared statements.
 * =====================================================
 */
class Database
{
    /** @var PDO|null */
    private static $instance = null;

    /** Devuelve la única conexión PDO de la aplicación. */
    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $name = $_ENV['DB_NAME'] ?? 'monchomania';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $host,
                $port,
                $name
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // prepared statements reales (anti SQLi)
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                // Asegura UTF-8 completo (acentos, ñ, emojis) al guardar y leer
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
                // Fechas en UTC: el navegador las muestra en la hora local del usuario.
                self::$instance->exec("SET time_zone = '+00:00'");
            } catch (PDOException $e) {
                http_response_code(500);
                if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                    die('Error de conexión a la base de datos: ' . $e->getMessage());
                }
                die('Error de conexión a la base de datos.');
            }
        }

        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}
}
