<?php
declare(strict_types=1);

namespace App;

/** Teachers opening and closing lesson slots. */
final class Slots
{
    /**
     * Open a slot (optionally repeating weekly). Returns how many were created / already existed.
     * @return array{created:int, skipped:int}
     */
    public static function open(array $teacher, string $date, int $order, int $subjectId, int $capacity, ?string $room, int $repeatWeeks = 1): array
    {
        if (!Policy::canManageSlots($teacher)) {
            throw new RuleException('err.forbidden');
        }
        if (!in_array($subjectId, $teacher['subject_ids'], true)) {
            throw new RuleException('slot.err.subject');
        }
        if (!valid_date($date)) {
            throw new RuleException('slot.err.date');
        }
        if ($capacity < 1 || $capacity > 30) {
            throw new RuleException('slot.err.capacity');
        }
        $bell = Repo::bell();
        if (!isset($bell[$order])) {
            throw new RuleException('slot.err.lesson');
        }
        $room = $room !== null ? trim($room) : null;
        $room = ($room === '' ? null : mb_substr((string)$room, 0, 20));
        $repeatWeeks = max(1, min(12, $repeatWeeks));

        $created = $skipped = 0;
        for ($i = 0; $i < $repeatWeeks; $i++) {
            $d = add_days($date, 7 * $i);
            if ((int)(new \DateTimeImmutable($d))->format('N') > 5) {
                throw new RuleException('slot.err.weekend');
            }
            if (Booking::startOf($d, $bell[$order]['starts_at']) <= Clock::now()) {
                if ($i === 0) {
                    throw new RuleException('slot.err.past');
                }
                $skipped++;
                continue;
            }
            $existing = Db::one(
                'SELECT id, closed_at FROM slots WHERE teacher_id = ? AND lesson_date = ? AND lesson_order = ?',
                [$teacher['id'], $d, $order]
            );
            if ($existing === null) {
                Db::run(
                    'INSERT INTO slots (teacher_id, subject_id, lesson_date, lesson_order, capacity, room, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$teacher['id'], $subjectId, $d, $order, $capacity, $room, Clock::now()->format('Y-m-d H:i:s')]
                );
                $created++;
            } elseif ($existing['closed_at'] !== null) {
                Db::run(
                    'UPDATE slots SET closed_at = NULL, subject_id = ?, capacity = ?, room = ? WHERE id = ?',
                    [$subjectId, $capacity, $room, $existing['id']]
                );
                $created++;
            } else {
                $skipped++;
            }
        }
        return ['created' => $created, 'skipped' => $skipped];
    }

    /** Close a slot nobody has booked. Closed slots disappear for students but stay in the history. */
    public static function close(array $teacher, int $slotId): void
    {
        $slot = Repo::slot($slotId);
        if (!$slot || (int)$slot['teacher_id'] !== $teacher['id'] || !Policy::canManageSlots($teacher)) {
            throw new RuleException('err.forbidden');
        }
        if ((int)$slot['taken'] > 0) {
            throw new RuleException('slot.err.has_bookings');
        }
        Db::run('UPDATE slots SET closed_at = ? WHERE id = ? AND closed_at IS NULL', [Clock::now()->format('Y-m-d H:i:s'), $slotId]);
    }
}
