<?php
declare(strict_types=1);

namespace App;

/** Booking, cancelling and attendance. All the booking rules are enforced here, in one place. */
final class Booking
{
    public static function startOf(string $date, string $startsAt): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date . ' ' . $startsAt . ':00');
    }

    public static function hasStarted(array $row): bool
    {
        return self::startOf((string)$row['lesson_date'], (string)$row['starts_at']) <= Clock::now();
    }

    /** Why a student can't book this slot right now, as a translation key (null = can book). */
    public static function blockReason(array $slot): ?string
    {
        $start = self::startOf((string)$slot['lesson_date'], (string)$slot['starts_at']);
        $now = Clock::now();
        if ($start <= $now) {
            return 'book.err.past';
        }
        if ($start <= $now->modify('+' . (int)env('BOOKING_CUTOFF_HOURS', 2) . ' hours')) {
            return 'book.err.cutoff';
        }
        return null;
    }

    /**
     * Book a seat for a student. Returns the new appointment id.
     * @throws RuleException with a translation key explaining which rule failed
     */
    public static function book(array $user, int $slotId): int
    {
        if (!Policy::canBook($user)) {
            throw new RuleException('err.forbidden');
        }
        return (int)Db::transaction(function () use ($user, $slotId): int {
            // Lock the slot row so two people booking the last seat at once are handled one by one.
            Db::val('SELECT id FROM slots WHERE id = ?' . Db::forUpdate(), [$slotId]);
            $slot = Repo::slot($slotId);
            if (!$slot || $slot['closed_at'] !== null || !$slot['teacher_active']) {
                throw new RuleException('book.err.closed');
            }
            if ($reason = self::blockReason($slot)) {
                throw new RuleException($reason, ['hours' => (int)env('BOOKING_CUTOFF_HOURS', 2)]);
            }
            $studentId = $user['id'];
            if (Db::val('SELECT 1 FROM appointments WHERE slot_id = ? AND student_id = ? AND is_active = 1', [$slotId, $studentId])) {
                throw new RuleException('book.err.already');
            }
            if ((int)$slot['taken'] >= (int)$slot['capacity']) {
                throw new RuleException('book.err.full');
            }
            if (Db::val(
                'SELECT 1 FROM appointments WHERE student_id = ? AND lesson_date = ? AND lesson_order = ? AND is_active = 1',
                [$studentId, $slot['lesson_date'], $slot['lesson_order']]
            )) {
                throw new RuleException('book.err.clash');
            }
            $monday = week_start((string)$slot['lesson_date']);
            $max = (int)env('BOOKING_MAX_PER_SUBJECT_WEEK', 3);
            $used = (int)Db::val(
                'SELECT COUNT(*) FROM appointments a JOIN slots sl ON sl.id = a.slot_id
                  WHERE a.student_id = ? AND a.is_active = 1 AND sl.subject_id = ? AND a.lesson_date BETWEEN ? AND ?',
                [$studentId, $slot['subject_id'], $monday, add_days($monday, 6)]
            );
            if ($used >= $max) {
                throw new RuleException('book.err.limit', ['max' => $max]);
            }
            try {
                $id = Db::insert(
                    'INSERT INTO appointments (slot_id, student_id, lesson_date, lesson_order, status, is_active, created_at)
                     VALUES (?, ?, ?, ?, ?, 1, ?)',
                    [$slotId, $studentId, $slot['lesson_date'], $slot['lesson_order'], 'booked', Clock::now()->format('Y-m-d H:i:s')]
                );
            } catch (\PDOException) {
                // The UNIQUE keys are the last line of defence against a race.
                throw new RuleException('book.err.taken');
            }
            Notify::to((int)$slot['teacher_id'], 'n.booked', [
                'student' => $user['full_name'], 'subject_slug' => $slot['subject_slug'], 'subject' => $slot['subject_name'],
                'date' => $slot['lesson_date'], 'time' => $slot['starts_at'],
            ], 'lessons.php');
            return $id;
        });
    }

    /** Cancel a booking. Nothing is deleted: the row is kept with status "cancelled". */
    public static function cancel(array $user, int $appointmentId): array
    {
        return Db::transaction(function () use ($user, $appointmentId): array {
            $a = Repo::appointment($appointmentId);
            if (!$a || !$a['is_active']) {
                throw new RuleException('err.notfound');
            }
            if (!Policy::canCancel($user, $a)) {
                throw new RuleException('err.forbidden');
            }
            if (self::hasStarted($a)) {
                throw new RuleException('cancel.err.started');
            }
            Db::run(
                "UPDATE appointments SET status = 'cancelled', is_active = NULL, cancelled_by = ?, cancelled_at = ?
                  WHERE id = ? AND is_active = 1",
                [$user['id'], Clock::now()->format('Y-m-d H:i:s'), $appointmentId]
            );
            $params = [
                'subject_slug' => $a['subject_slug'], 'subject' => $a['subject_name'],
                'date' => $a['lesson_date'], 'time' => $a['starts_at'],
                'student' => $a['student_name'], 'by' => $user['full_name'],
            ];
            if ($user['id'] === (int)$a['student_id']) {
                Notify::to((int)$a['teacher_id'], 'n.cancel_by_student', $params, 'lessons.php');
            } else {
                Notify::to((int)$a['student_id'], 'n.cancel_by_staff', $params, 'appointments.php?tab=cancelled');
            }
            return $a;
        });
    }

    /** Teacher marks a student as attended / no-show (or back to "booked"). Only once the lesson has started. */
    public static function mark(array $user, int $appointmentId, string $status): void
    {
        if (!in_array($status, ['attended', 'no_show', 'booked'], true)) {
            throw new RuleException('err.forbidden');
        }
        $a = Repo::appointment($appointmentId);
        if (!$a || !$a['is_active']) {
            throw new RuleException('err.notfound');
        }
        if (!Policy::canMarkAttendance($user, $a)) {
            throw new RuleException('err.forbidden');
        }
        if (!self::hasStarted($a)) {
            throw new RuleException('mark.err.notstarted');
        }
        Db::run('UPDATE appointments SET status = ? WHERE id = ? AND is_active = 1', [$status, $appointmentId]);
    }
}
