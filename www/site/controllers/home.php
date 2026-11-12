<?php

return function ($page) {
  return [
    'projects' => $page->children()->listed()->filterBy('intendedTemplate', 'project')->sortBy('num', 'asc'),
  ];
};
