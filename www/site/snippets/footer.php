<?php
$modified = max($site->modified(), filemtime($kirby->root('assets') . '/css/main.css'), filemtime($kirby->root('assets') . '/js/main.js'));
foreach ($site->index() as $entry) {
  $modified = max($modified, $entry->modified());
}
$updatedAt = (new DateTimeImmutable(getenv('SITE_UPDATED_AT') ?: '@' . $modified))
  ->setTimezone(new DateTimeZone('Europe/Amsterdam'));
?>
<footer class="site-footer">
  <p>Website was last updated <time datetime="<?= esc($updatedAt->format(DATE_ATOM), 'attr') ?>"><?= $updatedAt->format('d.m.Y') ?></time>, <span translate="no">Armand Demulder</span> forever</p>
  <?php if ($site->email()->isNotEmpty()): ?>
  <p><?= Html::email($site->email()->value(), 'Email Armand') ?></p>
  <?php endif ?>
</footer>
