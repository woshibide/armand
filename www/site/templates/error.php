<?php snippet('layout', slots: true) ?>
  <div class="page-content text-page">
    <h1 class="display-title"><?= $page->title()->esc() ?></h1>
    <?= $page->text()->kirbytext() ?>
    <p><a href="<?= esc($site->url(), 'attr') ?>">Back to projects</a></p>
  </div>
<?php endsnippet() ?>
