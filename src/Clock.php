<?php
declare(strict_types=1);

namespace App;

/** "Now" in one place, so tests can freeze time. */
final class Clock
{
    private static ?\DateTimeImmutable $fixed = null;

    public static function now(): \DateTimeImmutable
    {
        return self::$fixed ?? new \DateTimeImmutable('now');
    }

    public static function fix(?\DateTimeImmutable $now): void
    {
        self::$fixed = $now;
    }

    public static function today(): string
    {
        return self::now()->format('Y-m-d');
    }
}
