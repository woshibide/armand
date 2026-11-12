<?php if ($image): ?>
<?php $resized = $image->resize(1600); ?>
<figure>
  <img src="<?= esc($resized->url(), 'attr') ?>" width="<?= $resized->width() ?>" height="<?= $resized->height() ?>" alt="<?= $image->alt()->esc('attr') ?>" loading="<?= ($eager ?? false) ? 'eager' : 'lazy' ?>" decoding="async">
  <?php if ($image->caption()->isNotEmpty()): ?>
  <figcaption><?= $image->caption()->esc() ?></figcaption>
  <?php endif ?>
</figure>
<?php endif ?>
