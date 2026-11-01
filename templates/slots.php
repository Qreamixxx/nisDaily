<?php
$weekLabel = fmt_date($week) . ' – ' . fmt_date(add_days($week, 6));
?>
<h1><?= e(t('slots.title')) ?></h1>
<p class="muted"><?= e(t('slots.lead')) ?></p>

<?php if (!$mySubjects): ?>
    <section class="card flash flash-warn"><p><?= e(t('dash.no_subjects')) ?></p></section>
<?php else: ?>
<section class="card" aria-labelledby="open-h">
    <h2 id="open-h"><?= e(t('slots.open')) ?></h2>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="open">
        <div class="field"><label for="date"><?= e(t('slots.date')) ?></label>
            <input id="date" name="date" type="date" min="<?= e($today) ?>" value="<?= e($defaultDate) ?>" required></div>
        <div class="field"><label for="lesson"><?= e(t('slots.lesson')) ?></label>
            <select id="lesson" name="lesson"><?php foreach ($bell as $n => $b): ?>
                <option value="<?= (int)$n ?>"><?= e(t('slots.lesson_opt', ['n' => $n, 'time' => fmt_range($b['starts_at'], $b['ends_at'])])) ?></option>
            <?php endforeach; ?></select></div>
        <div class="field"><label for="subject"><?= e(t('col.subject')) ?></label>
            <select id="subject" name="subject"><?php foreach ($mySubjects as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= e(subject_label($s['slug'], $s['name'])) ?></option>
            <?php endforeach; ?></select></div>
        <div class="field"><label for="capacity"><?= e(t('slots.capacity')) ?></label>
            <input id="capacity" name="capacity" type="number" min="1" max="30" value="1" required></div>
        <div class="field"><label for="room"><?= e(t('col.room')) ?></label>
            <input id="room" name="room" type="text" maxlength="20" value="<?= e($defaultRoom) ?>"></div>
        <div class="field"><label for="repeat"><?= e(t('slots.repeat')) ?></label>
            <select id="repeat" name="repeat"><?php foreach ([1, 2, 3, 4, 6, 8, 12] as $n): ?>
                <option value="<?= $n ?>"><?= e(t('slots.repeat_opt', ['n' => $n])) ?></option><?php endforeach; ?></select></div>
        <div class="field align-end"><button class="btn btn-primary"><?= e(t('slots.open_btn')) ?></button></div>
    </form>
</section>
<?php endif; ?>

<div class="week-nav">
    <a class="btn btn-small" href="slots.php?week=<?= e(add_days($week, -7)) ?>">← <?= e(t('week.prev')) ?></a>
    <strong aria-live="polite"><?= e($weekLabel) ?></strong>
    <a class="btn btn-small" href="slots.php?week=<?= e(add_days($week, 7)) ?>"><?= e(t('week.next')) ?> →</a>
</div>
<?php if ($week !== $thisWeek): ?><p class="center"><a href="slots.php"><?= e(t('week.this')) ?></a></p><?php endif; ?>

<?php if (!$slots): ?>
    <section class="card"><p><?= e(t('slots.none')) ?></p></section>
<?php else: ?>
<div class="table-wrap">
<table>
    <caption class="sr-only"><?= e($weekLabel) ?></caption>
    <thead><tr>
        <th scope="col"><?= e(t('col.date')) ?></th><th scope="col"><?= e(t('col.time')) ?></th><th scope="col"><?= e(t('col.subject')) ?></th>
        <th scope="col"><?= e(t('col.room')) ?></th><th scope="col"><?= e(t('slots.seats')) ?></th><th scope="col"><?= e(t('col.student')) ?></th>
        <th scope="col"><span class="sr-only"><?= e(t('col.actions')) ?></span></th>
    </tr></thead>
    <tbody>
    <?php foreach ($slots as $s):
        $list = $bookings[(int)$s['id']] ?? [];
        $started = App\Booking::hasStarted($s); ?>
        <tr>
            <th scope="row"><?= e(fmt_date($s['lesson_date'])) ?></th>
            <td><?= e(fmt_range($s['starts_at'], $s['ends_at'])) ?></td>
            <td><?= e(subject_label($s['subject_slug'], $s['subject_name'])) ?></td>
            <td><?= e((string)$s['room']) ?></td>
            <td><?= e(t('lesson.booked_of', ['n' => count($list), 'c' => $s['capacity']])) ?></td>
            <td><?= e(implode(', ', array_column($list, 'student_name'))) ?></td>
            <td><?php if (!$list && !$started): ?>
                <form method="post" data-confirm="<?= e(t('slots.close_confirm')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="action" value="close"><input type="hidden" name="slot_id" value="<?= (int)$s['id'] ?>">
                    <button class="btn btn-small btn-danger"><?= e(t('slots.close_btn')) ?></button>
                </form>
            <?php elseif ($list && !$started): ?><span class="muted"><?= e(t('slots.cancel_first')) ?></span><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
