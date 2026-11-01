<?php
require __DIR__ . '/bootstrap.php';

use App\Repo;
use App\Role;

// Calendar file (.ics) with the user's upcoming lessons, for Google / Apple / Outlook calendars.
$user = require_login(Role::Student, Role::Parent, Role::Teacher);

if ($user['role'] === Role::Teacher) {
    $today = App\Clock::today();
    $rows = Repo::openSlots($today, add_days($today, 90), null, $user['id']);
    $events = array_map(fn($s) => [
        'uid' => 'slot-' . $s['id'], 'date' => $s['lesson_date'], 'start' => $s['starts_at'], 'end' => $s['ends_at'],
        'summary' => subject_label($s['subject_slug'], $s['subject_name']) . ' (' . $s['taken'] . '/' . $s['capacity'] . ')',
        'room' => (string)$s['room'],
    ], $rows);
} else {
    $ids = $user['role'] === Role::Student ? [$user['id']] : $user['children'];
    $rows = Repo::appointments(['student_ids' => $ids, 'tab' => 'upcoming', 'limit' => 300])['rows'];
    $events = array_map(fn($a) => [
        'uid' => 'appt-' . $a['id'], 'date' => $a['lesson_date'], 'start' => $a['starts_at'], 'end' => $a['ends_at'],
        'summary' => subject_label($a['subject_slug'], $a['subject_name']) . ' – ' . $a['teacher_name']
            . ($user['role'] === Role::Parent ? ' (' . $a['student_name'] . ')' : ''),
        'room' => (string)$a['room'],
    ], $rows);
}

$esc = fn(string $s) => str_replace(["\\", ";", ",", "\r\n", "\n"], ["\\\\", "\;", "\\,", "\\n", "\\n"], $s);
$utc = new DateTimeZone('UTC');
$out = ["BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//Nis Daily//EN", "CALSCALE:GREGORIAN"];
foreach ($events as $ev) {
    $s = (new DateTimeImmutable($ev['date'] . ' ' . $ev['start'] . ':00'))->setTimezone($utc)->format('Ymd\THis\Z');
    $e = (new DateTimeImmutable($ev['date'] . ' ' . $ev['end'] . ':00'))->setTimezone($utc)->format('Ymd\THis\Z');
    $out[] = 'BEGIN:VEVENT';
    $out[] = 'UID:' . $ev['uid'] . '@nisdaily';
    $out[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
    $out[] = 'DTSTART:' . $s;
    $out[] = 'DTEND:' . $e;
    $out[] = 'SUMMARY:' . $esc($ev['summary']);
    if ($ev['room'] !== '') {
        $out[] = 'LOCATION:' . $esc('Room ' . $ev['room']);
    }
    $out[] = 'END:VEVENT';
}
$out[] = 'END:VCALENDAR';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="nisdaily.ics"');
echo implode("\r\n", $out) . "\r\n";
