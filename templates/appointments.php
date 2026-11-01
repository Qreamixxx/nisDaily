<?php use App\Role; ?>
<div class="title-row">
    <h1><?= e($user['role'] === Role::Parent ? t('appt.title_parent') : t('nav.appointments')) ?></h1>
    <?php if ($hasCalendar): ?><a class="btn btn-small" href="ics.php"><?= e(t('appt.calendar')) ?></a><?php endif; ?>
</div>

<nav class="tabs" aria-label="<?= e(t('appt.tabs')) ?>">
    <?php foreach (['upcoming', 'past', 'cancelled'] as $tb): ?>
        <a href="<?= e(url_with(['tab' => $tb, 'page' => null])) ?>" <?= $tb === $tab ? 'class="active" aria-current="page"' : '' ?>><?= e(t('appt.tab.' . $tb)) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($user['role'] === Role::Curator): ?>
<form method="get" class="filters">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="field"><label for="q"><?= e(t('appt.search')) ?></label><input id="q" name="q" type="search" value="<?= e($q) ?>"></div>
    <div class="field"><label for="subject"><?= e(t('appt.subject')) ?></label>
        <select id="subject" name="subject"><option value=""><?= e(t('appt.all_subjects')) ?></option>
            <?php foreach ($subjects as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === (int)$subjectFilter ? 'selected' : '' ?>><?= e(subject_label($s['slug'], $s['name'])) ?></option><?php endforeach; ?>
        </select></div>
    <button class="btn"><?= e(t('filter.apply')) ?></button>
</form>
<?php endif; ?>

<?php if (!$rows): ?>
    <section class="card"><p><?= e(t('appt.empty.' . $tab)) ?>
        <?php if ($user['role'] === Role::Student && $tab === 'upcoming'): ?> <a href="subjects.php"><?= e(t('dash.book')) ?></a><?php endif; ?></p></section>
<?php else: ?>
<div class="table-wrap">
<table>
    <caption class="sr-only"><?= e(t('appt.tab.' . $tab)) ?></caption>
    <thead><tr>
        <th scope="col"><?= e(t('col.date')) ?></th><th scope="col"><?= e(t('col.time')) ?></th><th scope="col"><?= e(t('col.subject')) ?></th>
        <?php if ($showStudent): ?><th scope="col"><?= e(t('col.student')) ?></th><?php endif; ?>
        <th scope="col"><?= e(t('col.teacher')) ?></th><th scope="col"><?= e(t('col.room')) ?></th><th scope="col"><?= e(t('col.status')) ?></th><th scope="col"><span class="sr-only"><?= e(t('col.actions')) ?></span></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $a):
        $canCancel = $tab === 'upcoming' && App\Policy::canCancel($user, $a); ?>
        <tr>
            <th scope="row"><?= e(fmt_date($a['lesson_date'])) ?></th>
            <td><?= e(fmt_range($a['starts_at'], $a['ends_at'])) ?></td>
            <td><?= e(subject_label($a['subject_slug'], $a['subject_name'])) ?></td>
            <?php if ($showStudent): ?><td><?= e($a['student_name']) ?></td><?php endif; ?>
            <td><?= e($a['teacher_name']) ?></td>
            <td><?= e((string)$a['room']) ?></td>
            <td><?= status_badge((string)$a['status'], (bool)$a['is_active']) ?></td>
            <td><?php if ($canCancel): ?>
                <form method="post" data-confirm="<?= e(t('cancel.confirm')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <input type="hidden" name="return" value="<?= e(url_with([])) ?>">
                    <button class="btn btn-small btn-danger"><?= e(t('cancel.button')) ?></button>
                </form>
            <?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= pager_html($pg) ?>
<?php endif; ?>
