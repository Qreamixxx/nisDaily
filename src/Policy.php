<?php
declare(strict_types=1);

namespace App;

/**
 * Every "who is allowed to do what" rule lives here, in one place.
 * Pages and services call these instead of writing their own role checks.
 */
final class Policy
{
    public static function canBook(array $user): bool
    {
        return $user['role'] === Role::Student;
    }

    /** May this user look at this student's grades, attendance and bookings? */
    public static function canViewStudent(array $user, int $studentId): bool
    {
        return match ($user['role']) {
            Role::Student => $user['id'] === $studentId,
            Role::Parent  => in_array($studentId, $user['children'], true),
            Role::Teacher => true,
            Role::Curator => Scope::studentInScope($user, $studentId),
        };
    }

    /** $appt needs: student_id, teacher_id. (Time rules are checked by the Booking service.) */
    public static function canCancel(array $user, array $appt): bool
    {
        return match ($user['role']) {
            Role::Student => (int)$appt['student_id'] === $user['id'],
            Role::Teacher => (int)$appt['teacher_id'] === $user['id'],
            Role::Curator => Scope::studentInScope($user, (int)$appt['student_id']),
            Role::Parent  => false,
        };
    }

    public static function canMarkAttendance(array $user, array $appt): bool
    {
        return ($user['role'] === Role::Curator && Scope::studentInScope($user, (int)$appt['student_id']))
            || ($user['role'] === Role::Teacher && (int)$appt['teacher_id'] === $user['id']);
    }

    public static function canManageSlots(array $user): bool
    {
        return $user['role'] === Role::Teacher;
    }

    public static function canManageExam(array $user, int $subjectId): bool
    {
        return $user['role'] === Role::Curator
            || ($user['role'] === Role::Teacher && in_array($subjectId, $user['subject_ids'], true));
    }

    public static function canManageUsers(array $user): bool
    {
        return $user['role'] === Role::Curator;
    }

    /** May this curator edit, reset or deactivate this account? ($target comes from Users::find) */
    public static function canManageUser(array $actor, array $target): bool
    {
        if ($actor['role'] !== Role::Curator) {
            return false;
        }
        if (!Scope::isScoped($actor)) {
            return true;
        }
        return match ($target['role']) {
            'student' => Scope::studentInScope($actor, (int)$target['id']),
            'parent'  => (bool)array_filter($target['child_ids'], fn($c) => Scope::studentInScope($actor, (int)$c)),
            default   => false,   // teachers and curators are the head curator's business
        };
    }

    /** May this curator create an account of this kind? A scoped curator: students of their classes, parents of those students. */
    public static function canCreateUser(array $actor, Role $role, ?int $classId, array $childIds): bool
    {
        if ($actor['role'] !== Role::Curator) {
            return false;
        }
        if (!Scope::isScoped($actor)) {
            return true;
        }
        return match ($role) {
            Role::Student => Scope::classInScope($actor, $classId),
            Role::Parent  => $childIds && !array_filter($childIds, fn($c) => !Scope::studentInScope($actor, (int)$c)),
            default       => false,
        };
    }

    /** Lesson times, the classes list and other curators' class assignments: head curator only. */
    public static function canAdminister(array $user): bool
    {
        return Scope::isHead($user);
    }
}
