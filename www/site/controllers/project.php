<?php

return function ($page) {
  $projects = $page->siblings()->listed()->filterBy('intendedTemplate', 'project')->sortBy('num', 'asc');

  return [
    'previous' => $page->prev($projects),
    'next' => $page->next($projects),
  ];
};
