<h1><?= e(t('subj.title')) ?></h1>
<p class="muted"><?= e(t('subj.lead')) ?></p>
<ul class="grid grid-4 plain">
    <?php foreach ($subjects as $s):
        $label = subject_label($s['slug'], $s['name']);
        $n = (int)($free[$s['id']] ?? 0);
        $has = $n > 0; ?>
        <li>
            <?php if ($has): ?>
                <a class="subject-card" href="book.php?subject=<?= (int)$s['id'] ?>">
                    <strong><?= e($label) ?></strong>
                    <span><?= e(t('subj.free', ['n' => $n])) ?></span>
                </a>
            <?php else: ?>
                <a class="subject-card is-empty" href="book.php?subject=<?= (int)$s['id'] ?>">
                    <strong><?= e($label) ?></strong>
                    <span><?= e(t('subj.none')) ?></span>
                </a>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
