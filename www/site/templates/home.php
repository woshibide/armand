<?php snippet('layout', slots: true) ?>
  <h1 class="visually-hidden" translate="no"><?= $site->title()->esc() ?></h1>
  <?php if ($projects->isEmpty()): ?>
  <p class="empty-projects">No projects yet.</p>
  <?php else: ?>
  <p id="reel-instructions" class="visually-hidden">Scroll to explore projects. Use Page Up, Page Down or the arrow keys to scroll.</p>
  <section class="project-reel" aria-label="Projects" aria-describedby="reel-instructions">
    <div class="project-sequence">
      <?php $position = 0; ?>
      <?php foreach ($projects as $project): ?>
      <?php $position++; ?>
      <article class="project-frame" data-project="<?= esc($project->slug(), 'attr') ?>">
        <a class="project-frame-link" href="<?= esc($project->url(), 'attr') ?>">
          <?php snippet('project-visual', ['image' => $project->cover()->toFile(), 'eager' => $project->is($projects->first())]) ?>
          <h2 class="project-frame-title"><?= $project->title()->esc() ?></h2>
        </a>
        <span class="project-frame-position" aria-label="Project <?= $position ?> of <?= $projects->count() ?>"><?= str_pad((string)$position, 2, '0', STR_PAD_LEFT) ?> / <?= str_pad((string)$projects->count(), 2, '0', STR_PAD_LEFT) ?></span>
      </article>
      <?php endforeach ?>
    </div>
  </section>
  <?php endif ?>
<?php endsnippet() ?>
