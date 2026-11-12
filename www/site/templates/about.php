<?php snippet('layout', slots: true) ?>
  <div class="about-page page-content">
    <h1 class="visually-hidden"><?= $page->title()->esc() ?></h1>
    <div class="about-statement"><?= $page->text()->kirbytext() ?></div>
  </div>
<?php endsnippet() ?>
