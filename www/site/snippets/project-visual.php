<?php if ($image): ?>
<?php $resized = $image->resize(1600); ?>
<img class="project-visual" src="<?= esc($resized->url(), 'attr') ?>" width="<?= $resized->width() ?>" height="<?= $resized->height() ?>" alt="<?= $image->alt()->esc('attr') ?>" loading="<?= ($eager ?? false) ? 'eager' : 'lazy' ?>" decoding="async">
<?php else: ?>
<img class="project-visual project-visual--placeholder" src="<?= esc(url('assets/images/project-placeholder.svg'), 'attr') ?>" width="1600" height="1000" alt="" aria-hidden="true" decoding="async">
<?php endif ?>
