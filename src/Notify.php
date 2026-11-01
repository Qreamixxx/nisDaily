<?php
declare(strict_types=1);

namespace App;

/** In-app notifications. Stored as a translation key + values, rendered in the reader's language. */
final class Notify
{
    public static function to(int $userId, string $key, array $params = [], ?string $link = null): void
    {
        Db::run(
            'INSERT INTO notifications (user_id, message_key, params, link, created_at) VALUES (?, ?, ?, ?, ?)',
            [$userId, $key, json_encode($params, JSON_UNESCAPED_UNICODE), $link, Clock::now()->format('Y-m-d H:i:s')]
        );
    }

    public static function unread(int $userId): int
    {
        return (int)Db::val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    /** @return array<int, array{id:int, text:string, link:?string, is_read:int, created_at:string}> */
    public static function latest(int $userId, int $limit = 30): array
    {
        $rows = Db::all(
            'SELECT id, message_key, params, link, is_read, created_at FROM notifications
              WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
        return array_map(function (array $r): array {
            $p = json_decode((string)$r['params'], true) ?: [];
            if (isset($p['subject_slug'])) {
                $p['subject'] = subject_label((string)$p['subject_slug'], (string)($p['subject'] ?? ''));
            }
            if (isset($p['date']) && valid_date((string)$p['date'])) {
                $p['date'] = fmt_date((string)$p['date']);
            }
            return [
                'id' => (int)$r['id'], 'text' => t($r['message_key'], $p), 'link' => $r['link'],
                'is_read' => (int)$r['is_read'], 'created_at' => (string)$r['created_at'],
            ];
        }, $rows);
    }

    public static function markAllRead(int $userId): void
    {
        Db::run('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
    }
}
