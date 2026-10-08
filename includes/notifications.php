<?php
/**
 * =====================================================
 *  monchomania - Notificaciones de la comunidad
 *  Cada usuario tiene las suyas y se marcan como vistas
 *  cuando abre la campanita del menú.
 * =====================================================
 */

/** Crea una notificación para un usuario concreto. */
function notify_user(int $userId, string $tipo, string $mensaje, ?string $url = null): void
{
    if ($userId <= 0) {
        return;
    }
    ensure_app_tables();

    try {
        Database::connection()
            ->prepare('INSERT INTO notificaciones (id_usuario, tipo, mensaje, url) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $tipo, mb_substr($mensaje, 0, 255), $url]);
    } catch (Throwable $e) {
        // Una notificación nunca debe romper la acción principal.
    }
}

/** Crea la misma notificación para todos los usuarios activos. */
function notify_all(string $tipo, string $mensaje, ?string $url = null, ?int $exceptUserId = null): void
{
    ensure_app_tables();

    try {
        $sql    = 'INSERT INTO notificaciones (id_usuario, tipo, mensaje, url)
                   SELECT id, ?, ?, ? FROM usuarios WHERE estado = 1';
        $params = [$tipo, mb_substr($mensaje, 0, 255), $url];

        if ($exceptUserId !== null) {
            $sql     .= ' AND id <> ?';
            $params[] = $exceptUserId;
        }

        Database::connection()->prepare($sql)->execute($params);
    } catch (Throwable $e) {
        // Ídem: silencioso.
    }
}

/** Cantidad de notificaciones sin ver del usuario. */
function notifications_unread_count(int $userId): int
{
    ensure_app_tables();

    try {
        $stmt = Database::connection()
            ->prepare('SELECT COUNT(*) FROM notificaciones WHERE id_usuario = ? AND leida = 0');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Últimas notificaciones del usuario (primero las que no ha visto). */
function notifications_recent(int $userId, int $limit = 10): array
{
    ensure_app_tables();

    try {
        $stmt = Database::connection()->prepare(
            'SELECT id, tipo, mensaje, url, leida, fecha_creacion
               FROM notificaciones
              WHERE id_usuario = ?
              ORDER BY leida ASC, fecha_creacion DESC
              LIMIT ' . (int) $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** Marca como vistas todas las notificaciones del usuario. */
function notifications_mark_all_read(int $userId): void
{
    ensure_app_tables();

    try {
        Database::connection()
            ->prepare('UPDATE notificaciones SET leida = 1 WHERE id_usuario = ? AND leida = 0')
            ->execute([$userId]);
    } catch (Throwable $e) {
        // Silencioso.
    }
}

/** Icono según el tipo de notificación. */
function notification_icon(string $tipo): string
{
    $iconos = [
        'noticia'    => '📰',
        'comentario' => '💬',
        'saludo'     => '🎂',
        'evento'     => '📌',
    ];
    return $iconos[$tipo] ?? '🔔';
}
