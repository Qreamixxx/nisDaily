<?php
use App\Role;

/** One lesson line: "Wed, 5 Nov, 13:15–13:55 · Informatics · Daniyar · room 204" */
$lesson = function (array $a): string {
    $room = $a['room'] ? ' · ' . t('lesson.room', ['room' => $a['room']]) : '';
    return '<strong>' . e(subject_label($a['subject_slug'], $a['subject_name'])) . '</strong><br>'
        . e(when_label($a['lesson_date'], $a['starts_at'], $a['ends_at'])) . '<br>'
        . '<span class="muted">' . e($a['teacher_name']) . $room . '</span>';
};
$gradeLine = function (?array $g): string {
    if (!$g) {
        return '<p class="muted">' . e(t('dash.no_grades')) . '</p>';
    }
    return '<p><strong class="big-num">' . e(num($g['score'])) . '</strong> / ' . e(num($g['max_score']))
        . '</p><p class="muted">' . e($g['exam_name']) . ' · ' . e(subject_label($g['subject_slug'], $g['subject_name'])) . '</p>';
};
?>
<h1><?= e(t('dash.welcome', ['name' => $user['full_name']])) ?></h1>

<?php if ($notes): ?>
<section class="card note-card" aria-labelledby="notes-h">
    <h2 id="notes-h"><?= e(t('notif.title')) ?></h2>
    <ul class="plain">
        <?php foreach ($notes as $n): ?><li><?= e($n['text']) ?></li><?php endforeach; ?>
    </ul>
    <a href="notifications.php"><?= e(t('dash.all_notes')) ?></a>
</section>
<?php endif; ?>

<?php if ($user['role'] === Role::Student): ?>
    <div class="grid grid-2">
        <section class="card" aria-labelledby="next-h">
            <h2 id="next-h"><?= e(t('dash.next')) ?></h2>
            <?php if ($mine['next']): ?>
                <p class="featured"><?= $lesson($mine['next'][0]) ?></p>
                <?php if (count($mine['next']) > 1): ?>
                    <h3><?= e(t('dash.then')) ?></h3>
                    <ul class="plain"><?php foreach (array_slice($mine['next'], 1) as $a): ?>
                        <li><?= e(subject_label($a['subject_slug'], $a['subject_name'])) ?> · <?= e(when_label($a['lesson_date'], $a['starts_at'], $a['ends_at'])) ?></li>
                    <?php endforeach; ?></ul>
                <?php endif; ?>
                <p class="muted"><?= e(t('dash.upcoming_count', ['n' => $mine['upcoming']])) ?></p>
            <?php else: ?>
                <p class="muted"><?= e(t('dash.none_upcoming')) ?></p>
            <?php endif; ?>
            <p class="actions">
                <a class="btn btn-primary" href="subjects.php"><?= e(t('dash.book')) ?></a>
                <a class="btn" href="appointments.php"><?= e(t('nav.appointments')) ?></a>
            </p>
        </section>
        <section class="card" aria-labelledby="grade-h">
            <h2 id="grade-h"><?= e(t('dash.latest_grade')) ?></h2>
            <?= $gradeLine($mine['grade']) ?>
            <p><a class="btn" href="statistics.php"><?= e(t('nav.stats')) ?></a></p>
        </section>
    </div>

<?php elseif ($user['role'] === Role::Parent): ?>
    <?php if (!$kids): ?>
        <section class="card"><p><?= e(t('dash.no_children')) ?></p></section>
    <?php endif; ?>
    <?php foreach ($kids as $k): ?>
        <section class="card" aria-labelledby="kid-<?= (int)$k['student']['id'] ?>">
            <h2 id="kid-<?= (int)$k['student']['id'] ?>"><?= e($k['student']['full_name']) ?> <small class="muted"><?= e((string)$k['student']['class_name']) ?></small></h2>
            <div class="grid grid-2">
                <div>
                    <h3><?= e(t('dash.next')) ?></h3>
                    <?php if ($k['next']): ?><p class="featured"><?= $lesson($k['next'][0]) ?></p>
                    <?php else: ?><p class="muted"><?= e(t('dash.none_upcoming')) ?></p><?php endif; ?>
                </div>
                <div>
                    <h3><?= e(t('dash.latest_grade')) ?></h3>
                    <?= $gradeLine($k['grade']) ?>
                </div>
            </div>
            <p class="actions">
                <a class="btn" href="appointments.php"><?= e(t('nav.appointments')) ?></a>
                <a class="btn" href="statistics.php?student=<?= (int)$k['student']['id'] ?>"><?= e(t('nav.stats')) ?></a>
            </p>
        </section>
    <?php endforeach; ?>

<?php elseif ($user['role'] === Role::Teacher): ?>
    <?php if (!$user['subject_ids']): ?>
        <section class="card flash flash-warn"><p><?= e(t('dash.no_subjects')) ?></p></section>
    <?php endif; ?>
    <div class="grid grid-2">
        <section class="card" aria-labelledby="today-h">
            <h2 id="today-h"><?= e(t('dash.today')) ?></h2>
            <?php if ($today): ?>
                <ul class="plain"><?php foreach ($today as $s): $n = count($bookings[(int)$s['id']] ?? []); ?>
                    <li><strong><?= e(fmt_range($s['starts_at'], $s['ends_at'])) ?></strong> · <?= e(subject_label($s['subject_slug'], $s['subject_name'])) ?>
                        · <?= e(t('lesson.booked_of', ['n' => $n, 'c' => $s['capacity']])) ?></li>
                <?php endforeach; ?></ul>
            <?php else: ?><p class="muted"><?= e(t('dash.no_today')) ?></p><?php endif; ?>
        </section>
        <section class="card" aria-labelledby="todo-h">
            <h2 id="todo-h"><?= e(t('dash.todo')) ?></h2>
            <p><strong class="big-num"><?= (int)$toMark ?></strong> <?= e(t('dash.to_mark')) ?></p>
            <p><strong class="big-num"><?= (int)$openCount ?></strong> <?= e(t('dash.open_slots')) ?></p>
            <p class="actions">
                <a class="btn btn-primary" href="lessons.php"><?= e(t('nav.lessons')) ?></a>
                <a class="btn" href="slots.php"><?= e(t('nav.slots')) ?></a>
            </p>
        </section>
    </div>

<?php else: ?>
    <?php if (!empty($pendingRegs)): ?>
        <p class="card note-card"><strong><?= e(t('dash.requests', ['n' => (int)$pendingRegs])) ?></strong> · <a href="registrations.php"><?= e(t('nav.registrations')) ?></a></p>
    <?php endif; ?>
    <p class="muted"><?= e($classNames ? t('dash.your_classes', ['classes' => implode(', ', $classNames)]) : t('dash.all_classes')) ?></p>
    <div class="grid grid-4">
        <?php foreach ($tileRoles as $r): ?>
            <section class="card tile"><strong class="big-num"><?= (int)($counts[$r] ?? 0) ?></strong><span><?= e(t('role.' . $r . 's')) ?></span></section>
        <?php endforeach; ?>
        <section class="card tile"><strong class="big-num"><?= (int)$upcoming ?></strong><span><?= e(t('dash.upcoming_bookings')) ?></span></section>
        <section class="card tile"><strong class="big-num"><?= (int)$openSlots ?></strong><span><?= e(t('dash.open_week')) ?></span></section>
    </div>
    <p class="actions">
        <a class="btn btn-primary" href="users.php"><?= e(t('nav.users')) ?></a>
        <a class="btn" href="appointments.php"><?= e(t('nav.appointments')) ?></a>
        <a class="btn" href="statistics.php"><?= e(t('nav.stats')) ?></a>
        <?php if (!$classNames): ?><a class="btn" href="classes.php"><?= e(t('nav.classes')) ?></a><?php endif; ?>
    </p>
<?php endif; ?>
