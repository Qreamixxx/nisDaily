<?php use App\Role; ?>
<h1><?= e(t('stats.title')) ?></h1>

<?php if ($staff): ?>
<nav class="tabs" aria-label="<?= e(t('stats.title')) ?>">
    <a href="statistics.php?view=classes&amp;subject=<?= (int)$subjectId ?>" <?= $view === 'classes' ? 'class="active" aria-current="page"' : '' ?>><?= e(t('stats.tab_classes')) ?></a>
    <a href="statistics.php?view=student" <?= $view === 'student' ? 'class="active" aria-current="page"' : '' ?>><?= e(t('stats.tab_students')) ?></a>
</nav>
<?php endif; ?>

<?php if ($view === 'classes'): ?>
    <p class="muted"><?= e(t('stats.classes_help')) ?></p>
    <?php if (!$subjectOptions): ?>
        <section class="card"><p class="muted"><?= e(t('stats.no_subjects')) ?></p></section>
    <?php else: ?>
    <form method="get" class="filters">
        <input type="hidden" name="view" value="classes">
        <div class="field"><label for="subject"><?= e(t('col.subject')) ?></label>
            <select id="subject" name="subject">
                <?php foreach ($subjectOptions as $o): ?>
                    <option value="<?= (int)$o['id'] ?>" <?= (int)$o['id'] === $subjectId ? 'selected' : '' ?>><?= e(subject_label($o['slug'], $o['name'])) ?></option>
                <?php endforeach; ?>
            </select></div>
        <button class="btn"><?= e(t('stats.show')) ?></button>
    </form>

    <?php if (!$classCards): ?>
        <section class="card"><p class="muted"><?= e(t('stats.no_class_data')) ?></p></section>
    <?php endif; ?>
    <div class="grid grid-2">
    <?php foreach ($classCards as $i => $card): ?>
        <section class="card" aria-labelledby="c-<?= $i ?>">
            <h2 id="c-<?= $i ?>"><?= e(t('stats.class_title', ['class' => $card['class']['name']])) ?> <small class="muted">(<?= (int)$card['class']['students'] ?> <?= e(t('role.students')) ?>)</small></h2>
            <?= $card['chart'] ?>
            <p class="legend-line"><span class="dot dot-bar"></span> <?= e(t('stats.legend_class')) ?> (%)</p>
            <div class="table-wrap"><table>
                <caption class="sr-only"><?= e(t('stats.class_title', ['class' => $card['class']['name']])) ?></caption>
                <thead><tr><th scope="col"><?= e(t('exams.name')) ?></th><th scope="col"><?= e(t('stats.graded')) ?></th><th scope="col"><?= e(t('stats.avg_short')) ?></th><th scope="col"><?= e(t('stats.lowest')) ?></th><th scope="col"><?= e(t('stats.highest')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($card['exams'] as $x): ?>
                    <tr><th scope="row"><?= e($x['name']) ?> <small class="muted"><?= e(fmt_date((string)$x['exam_date'])) ?></small></th>
                        <td><?= (int)$x['graded'] ?></td><td><?= e(num($x['avg_pct'])) ?>%</td><td><?= e(num($x['min_pct'])) ?>%</td><td><?= e(num($x['max_pct'])) ?>%</td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </section>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php elseif (!$student): ?>
    <section class="card"><p><?= e($user['role'] === Role::Parent ? t('dash.no_children') : t('stats.no_students')) ?></p></section>
<?php else: ?>

<form method="get" class="filters">
    <?php if ($staff): ?><input type="hidden" name="view" value="student"><?php endif; ?>
    <?php if (count($choices) > 1): ?>
    <div class="field"><label for="student"><?= e(t('stats.student')) ?></label>
        <select id="student" name="student">
            <?php foreach ($choices as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$student['id'] ? 'selected' : '' ?>><?= e($c['full_name']) ?><?= $c['class_name'] ? ' (' . e($c['class_name']) . ')' : '' ?></option>
            <?php endforeach; ?>
        </select></div>
    <?php else: ?><input type="hidden" name="student" value="<?= (int)$student['id'] ?>"><?php endif; ?>
    <div class="field"><label for="subject"><?= e(t('col.subject')) ?></label>
        <select id="subject" name="subject">
            <?php if ($allowAllSubjects): ?><option value="0"><?= e($teacherOnly ? t('stats.all_my_subjects') : t('stats.all_subjects')) ?></option><?php endif; ?>
            <?php foreach ($subjectOptions as $o): ?>
                <option value="<?= (int)$o['id'] ?>" <?= (int)$o['id'] === $subjectId ? 'selected' : '' ?>><?= e(subject_label($o['slug'], $o['name'])) ?></option>
            <?php endforeach; ?>
        </select></div>
    <button class="btn"><?= e(t('stats.show')) ?></button>
</form>

<h2><?= e($student['full_name']) ?> <small class="muted"><?= e((string)$student['class_name']) ?></small></h2>
<?php if ($teacherOnly): ?><p class="muted"><?= e(t('stats.teacher_scope')) ?></p><?php endif; ?>

<div class="grid grid-4">
    <section class="card tile"><strong class="big-num"><?= $avg !== null ? e(num($avg)) . '%' : '–' ?></strong><span><?= e(t('stats.avg')) ?></span></section>
    <section class="card tile"><strong class="big-num"><?= count($grades) ?></strong><span><?= e(t('stats.exams_taken')) ?></span></section>
    <section class="card tile"><strong class="big-num"><?= (int)$attended ?></strong><span><?= e(t('stats.attended')) ?></span></section>
    <section class="card tile"><strong class="big-num"><?= $rate !== null ? (int)$rate . '%' : '–' ?></strong><span><?= e(t('stats.rate')) ?></span></section>
</div>

<div class="grid grid-2">
    <section class="card" aria-labelledby="g-h">
        <h2 id="g-h"><?= e(t('stats.progress')) ?></h2>
        <?php if ($grades): ?>
            <?= $charts['grades'] ?>
            <p class="legend-line"><span class="dot dot-bar"></span> <?= e(t('stats.legend_student')) ?> · <span class="dot dot-ref"></span> <?= e(t('stats.legend_class')) ?></p>
            <div class="table-wrap"><table>
                <caption class="sr-only"><?= e(t('stats.grades')) ?></caption>
                <thead><tr><th scope="col"><?= e(t('exams.name')) ?></th><th scope="col"><?= e(t('col.subject')) ?></th><th scope="col"><?= e(t('stats.score')) ?></th><th scope="col"><?= e(t('stats.class_avg')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($grades as $g): ?>
                    <tr>
                        <th scope="row"><?= e($g['exam_name']) ?></th>
                        <td><?= e(subject_label($g['subject_slug'], $g['subject_name'])) ?></td>
                        <td><?= e(num($g['score'])) ?>/<?= e(num($g['max_score'])) ?> (<?= e(num($g['pct'])) ?>%)</td>
                        <td><?= $g['class_pct'] !== null ? e(num($g['class_pct'])) . '%' : '–' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php else: ?><p class="muted"><?= e(t('stats.no_grades')) ?></p><?php endif; ?>
    </section>

    <section class="card" aria-labelledby="a-h">
        <h2 id="a-h"><?= e(t('stats.attendance')) ?></h2>
        <?php if (isset($charts['attendance'])): ?>
            <?= $charts['attendance'] ?>
            <p class="muted"><?= e(t('stats.att_note', ['a' => $attended, 'm' => $missed])) ?></p>
            <div class="table-wrap"><table>
                <caption class="sr-only"><?= e(t('stats.attendance')) ?></caption>
                <thead><tr><th scope="col"><?= e(t('stats.week_of')) ?></th><th scope="col"><?= e(t('stats.attended')) ?></th></tr></thead>
                <tbody><?php foreach ($weeks as $monday => $n): ?>
                    <tr><th scope="row"><?= e(fmt_date($monday)) ?></th><td><?= (int)$n ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php else: ?><p class="muted"><?= e(t('stats.no_att')) ?></p><?php endif; ?>
    </section>
</div>
<?php endif; ?>
