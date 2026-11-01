<?php
use App\Role;

$end = add_days($week, 6);
$weekLabel = fmt_date($week) . ' – ' . fmt_date($end);
$isStudent = $user['role'] === Role::Student;
?>
<h1><?= e($label) ?></h1>

<div class="week-nav">
    <?php if ($prev): ?><a class="btn btn-small" href="book.php?subject=<?= (int)$subject['id'] ?>&amp;week=<?= e($prev) ?>">← <?= e(t('week.prev')) ?></a><?php else: ?><span></span><?php endif; ?>
    <strong aria-live="polite"><?= e($weekLabel) ?></strong>
    <?php if ($next): ?><a class="btn btn-small" href="book.php?subject=<?= (int)$subject['id'] ?>&amp;week=<?= e($next) ?>"><?= e(t('week.next')) ?> →</a><?php else: ?><span></span><?php endif; ?>
</div>
<?php if ($week !== $thisWeek): ?><p class="center"><a href="book.php?subject=<?= (int)$subject['id'] ?>"><?= e(t('week.this')) ?></a></p><?php endif; ?>

<ul class="legend" aria-label="<?= e(t('legend.title')) ?>">
    <li><span class="dot dot-free"></span> <?= e(t('slot.state.free')) ?></li>
    <li><span class="dot dot-mine"></span> <?= e(t('slot.state.mine')) ?></li>
    <li><span class="dot dot-full"></span> <?= e(t('slot.state.full')) ?></li>
    <li><span class="dot dot-cutoff"></span> <?= e(t('slot.state.cutoff_short')) ?></li>
</ul>

<div class="week-grid">
<?php foreach ($days as $date => $list): ?>
    <section class="day-col <?= $date === $today ? 'is-today' : '' ?>" aria-labelledby="d-<?= e($date) ?>">
        <h2 id="d-<?= e($date) ?>"><?= e(fmt_date($date)) ?></h2>
        <?php if (!$list): ?><p class="empty"><?= e(t('book.no_slots')) ?></p><?php endif; ?>
        <?php foreach ($list as $s):
            $left = max(0, (int)$s['capacity'] - (int)$s['taken']); ?>
            <article class="slot slot-<?= e($s['state']) ?>">
                <p class="slot-time"><?= e(fmt_range($s['starts_at'], $s['ends_at'])) ?></p>
                <p><?= e($s['teacher_name']) ?><?= $s['room'] ? ' · ' . e(t('lesson.room', ['room' => $s['room']])) : '' ?></p>
                <p class="muted"><?= e(t('slot.seats_left', ['n' => $left, 'c' => $s['capacity']])) ?></p>

                <?php if ($isStudent && $s['state'] === 'free'): ?>
                    <form method="post" data-confirm="<?= e(t('book.confirm', [
                        'subject' => $label, 'when' => when_label($s['lesson_date'], $s['starts_at'], $s['ends_at']), 'teacher' => $s['teacher_name'],
                    ])) ?>">
                        <?= csrf_field() ?><input type="hidden" name="slot_id" value="<?= (int)$s['id'] ?>">
                        <button class="btn btn-primary btn-small"><?= e(t('slot.book')) ?></button>
                    </form>
                <?php elseif ($s['state'] === 'mine'): ?>
                    <p><span class="badge badge-booked"><?= e(t('slot.state.mine')) ?></span> <a href="appointments.php"><?= e(t('slot.manage')) ?></a></p>
                <?php elseif ($s['state'] === 'full'): ?>
                    <p><span class="badge badge-full"><?= e(t('slot.state.full')) ?></span></p>
                <?php elseif ($s['state'] === 'cutoff'): ?>
                    <p><span class="badge badge-cutoff"><?= e(t('slot.state.cutoff', ['hours' => (int)env('BOOKING_CUTOFF_HOURS', 2)])) ?></span></p>
                <?php elseif ($s['state'] === 'past'): ?>
                    <p><span class="badge badge-cancelled"><?= e(t('slot.state.past')) ?></span></p>
                <?php endif; ?>

                <?php if (!$isStudent && !empty($bookings[(int)$s['id']])): ?>
                    <ul class="plain small"><?php foreach ($bookings[(int)$s['id']] as $b): ?><li><?= e($b['student_name']) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>
</div>
<p><a href="subjects.php">← <?= e(t('book.all_subjects')) ?></a></p>
