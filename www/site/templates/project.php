<?php snippet('layout', slots: true) ?>
  <article class="project-page page-content">
    <h1 class="display-title"><?= $page->title()->esc() ?></h1>
    <?php if ($page->summary()->isNotEmpty()): ?>
    <p class="project-summary"><?= $page->summary()->esc() ?></p>
    <?php endif ?>
    <dl class="project-meta">
      <?php foreach (['category' => 'Discipline', 'year' => 'Year', 'role' => 'Role'] as $field => $label): ?>
      <?php if ($page->content()->get($field)->isNotEmpty()): ?>
      <dt><?= esc($label) ?></dt>
      <dd><?= $page->content()->get($field)->esc() ?></dd>
      <?php endif ?>
      <?php endforeach ?>
    </dl>
    <div class="project-cover"><?php snippet('project-visual', ['image' => $page->cover()->toFile(), 'eager' => true]) ?></div>
    <div class="project-story"><?= $page->text()->kirbytext() ?></div>
    <?php foreach ($page->gallery()->toFiles() as $image): ?>
    <?php snippet('cover', ['image' => $image]) ?>
    <?php endforeach ?>
  </article>
  <nav class="project-navigation" aria-label="Project navigation">
    <ul>
      <?php if ($previous): ?>
      <li><a href="<?= esc($previous->url(), 'attr') ?>" rel="prev">Previous project: <?= $previous->title()->esc() ?></a></li>
      <?php endif ?>
      <?php if ($next): ?>
      <li><a href="<?= esc($next->url(), 'attr') ?>" rel="next">Next project: <?= $next->title()->esc() ?></a></li>
      <?php endif ?>
      <li><a href="<?= esc($site->url(), 'attr') ?>">All projects</a></li>
    </ul>
  </nav>
<?php endsnippet() ?>
