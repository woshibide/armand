<?php

return [
  'debug' => PHP_SAPI === 'cli-server',
  'panel' => ['install' => PHP_SAPI === 'cli-server'],
];
