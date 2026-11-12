<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#000000">
  <title><?= $page->isHomePage() ? $site->title()->esc() : $page->title()->esc() . ' | ' . $site->title()->esc() ?></title>
  <meta name="description" content="<?= $page->description()->or($site->description())->esc('attr') ?>">
  <?php if ($page->isErrorPage()): ?>
  <meta name="robots" content="noindex">
  <?php else: ?>
  <link rel="canonical" href="<?= esc($page->url(), 'attr') ?>">
  <?php endif ?>
  <link rel="preload" href="<?= esc(url('assets/fonts/inter-tight-regular.woff2'), 'attr') ?>" as="font" type="font/woff2" crossorigin>
  <?= css('assets/css/main.css') ?>
  <?= js('assets/js/main.js', ['type' => 'module']) ?>
</head>
<body class="<?= $page->isHomePage() ? 'is-home' : 'is-page' ?>">
  <a class="skip-link" href="#main">Skip to content</a>
  <header class="site-header">
    <a class="site-name" href="<?= esc($site->url(), 'attr') ?>" translate="no"><?= $site->title()->esc() ?></a>
    <nav aria-label="Main navigation">
      <ul>
        <?php foreach ($site->children()->listed() as $item): ?>
        <?php if ($item->isHomePage()) continue; ?>
        <li>
          <a href="<?= esc($item->url(), 'attr') ?>"<?php if ($item->isActive()): ?> aria-current="page"<?php elseif ($item->isOpen()): ?> aria-current="true"<?php endif ?>><?= $item->title()->esc() ?></a>
        </li>
        <?php endforeach ?>
      </ul>
    </nav>
  </header>
  <main id="main" class="site-main" tabindex="-1"<?= $page->isHomePage() ? ' data-project-scroll' : '' ?>>
    <?= $slot ?>
  </main>
  <?php if (!$page->isHomePage()): ?>
  <?php snippet('footer') ?>
  <?php endif ?>
</body>
</html>
