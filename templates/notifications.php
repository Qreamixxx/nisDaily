<h1><?= e(t('notif.title')) ?></h1>
<?php if (!$items): ?>
    <section class="card"><p><?= e(t('notif.none')) ?></p></section>
<?php else: ?>
<ul class="notes">
    <?php foreach ($items as $n): ?>
        <li class="card <?= $n['is_read'] ? '' : 'is-new' ?>">
            <?php if (!$n['is_read']): ?><span class="badge badge-booked"><?= e(t('notif.new')) ?></span><?php endif; ?>
            <?= $n['link'] ? '<a href="' . e($n['link']) . '">' . e($n['text']) . '</a>' : e($n['text']) ?>
            <small class="muted"><?= e($n['created_at']) ?></small>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
