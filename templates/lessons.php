<?php
/** One lesson block with its students. $mark = show attendance buttons. */
$block = function (array $s, bool $mark): void { ?>
    <article class="card lesson" id="lesson-<?= (int)$s['id'] ?>">
        <h3><?= e(subject_label($s['subject_slug'], $s['subject_name'])) ?> · <?= e(when_label($s['lesson_date'], $s['starts_at'], $s['ends_at'])) ?></h3>
        <p class="muted"><?= e(t('lesson.booked_of', ['n' => count($s['students']), 'c' => $s['capacity']])) ?><?= $s['room'] ? ' · ' . e(t('lesson.room', ['room' => $s['room']])) : '' ?></p>
        <?php if ($s['students']): ?>
        <ul class="roster">
            <?php foreach ($s['students'] as $b): ?>
                <li>
                    <span><?= e($b['student_name']) ?> <?= status_badge((string)$b['status']) ?></span>
                    <?php if ($mark): ?>
                    <form method="post" class="inline-actions">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="slot_id" value="<?= (int)$s['id'] ?>">
                        <button class="btn btn-small <?= $b['status'] === 'attended' ? 'btn-primary' : '' ?>" name="status" value="attended"><?= e(t('mark.attended')) ?></button>
                        <button class="btn btn-small <?= $b['status'] === 'no_show' ? 'btn-danger' : '' ?>" name="status" value="no_show"><?= e(t('mark.no_show')) ?></button>
                    </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </article>
<?php };
?>
<h1><?= e(t('lessons.title')) ?></h1>

<section aria-labelledby="needs-h">
    <h2 id="needs-h"><?= e(t('lessons.needs')) ?></h2>
    <?php if (!$needs): ?><p class="muted"><?= e(t('lessons.needs_none')) ?></p><?php endif; ?>
    <?php foreach ($needs as $s) { $block($s, true); } ?>
</section>

<section aria-labelledby="up-h">
    <h2 id="up-h"><?= e(t('lessons.upcoming')) ?></h2>
    <?php if (!$upcoming): ?><p class="muted"><?= e(t('lessons.upcoming_none')) ?> <a href="slots.php"><?= e(t('nav.slots')) ?></a></p><?php endif; ?>
    <?php foreach ($upcoming as $s) { $s['students'] ??= []; $block($s, false); } ?>
</section>

<?php if ($done): ?>
<section aria-labelledby="done-h">
    <h2 id="done-h"><?= e(t('lessons.done')) ?></h2>
    <?php foreach ($done as $s) { $block($s, true); } ?>
</section>
<?php endif; ?>
