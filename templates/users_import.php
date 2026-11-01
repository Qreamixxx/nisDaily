<h1><?= e(t('import.title')) ?></h1>
<?php if ($error): ?><p class="form-error" role="alert"><?= e($error) ?></p><?php endif; ?>

<?php if ($result): ?>
<section class="card" aria-labelledby="res-h">
    <h2 id="res-h"><?= e(t('import.done', ['n' => count($result['created'])])) ?></h2>
    <?php if ($result['created']): ?>
        <p class="flash flash-warn"><?= e(t('import.copy_now')) ?></p>
        <div class="table-wrap"><table>
            <caption class="sr-only"><?= e(t('import.done', ['n' => count($result['created'])])) ?></caption>
            <thead><tr><th scope="col"><?= e(t('col.name')) ?></th><th scope="col"><?= e(t('login.iin')) ?></th><th scope="col"><?= e(t('col.role')) ?></th><th scope="col"><?= e(t('import.temp')) ?></th></tr></thead>
            <tbody><?php foreach ($result['created'] as $c): ?>
                <tr><th scope="row"><?= e($c['name']) ?></th><td><?= e($c['iin']) ?></td><td><?= e(role_label($c['role'])) ?></td><td><code><?= e($c['password']) ?></code></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
    <?php if ($result['errors']): ?>
        <h3><?= e(t('import.errors')) ?></h3>
        <ul><?php foreach ($result['errors'] as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="card">
    <p><?= e(t('import.help')) ?></p>
    <pre class="code">iin,full_name,role,class,room,subjects,children
031001500123,Aibek Nurov,student,10A,,,
860101300456,Samal Aitova,teacher,,210,math|physics,
780505400789,Gulmira Nurova,parent,,,,031001500123</pre>
    <form method="post" enctype="multipart/form-data" class="stack">
        <?= csrf_field() ?>
        <div class="field"><label for="file"><?= e(t('import.file')) ?></label><input id="file" name="file" type="file" accept=".csv,text/csv"></div>
        <div class="field"><label for="csv"><?= e(t('import.paste')) ?></label><textarea id="csv" name="csv" rows="6" spellcheck="false"></textarea></div>
        <button class="btn btn-primary"><?= e(t('import.go')) ?></button>
    </form>
</section>
