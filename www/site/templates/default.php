<?php snippet('layout', slots: true) ?>
  <div class="page-content text-page">
    <h1 class="display-title"><?= $page->title()->esc() ?></h1>
    <?= $page->text()->kirbytext() ?>
  </div>
<?php endsnippet() ?>
