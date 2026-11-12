<?php

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

use Kirby\Cms\App;
use Kirby\Data\Data;

require dirname(__DIR__) . '/kirby/bootstrap.php';

$root = dirname(__DIR__);
$destination = $root . '/dist';
$errors = [];

foreach (['site', 'scripts'] as $directory) {
  $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
  foreach ($files as $file) {
    if ($file->getExtension() === 'php') {
      try {
        token_get_all(file_get_contents($file->getPathname()), TOKEN_PARSE);
      } catch (ParseError $error) {
        $errors[] = $file->getPathname() . ': ' . $error->getMessage();
      }
    } elseif ($file->getExtension() === 'yml') {
      Data::read($file->getPathname());
    }
  }
}

$app = new App(['roots' => ['index' => $root]]);
foreach ($app->site()->index() as $page) {
  $page->blueprint()->toArray();
}
$app->site()->blueprint()->toArray();
Data::read(dirname($root) . '/.github/workflows/pages.yml');

if (!is_file($destination . '/index.html')) {
  fwrite(STDERR, "No static build found. Run composer build before composer check.\n");
  exit(1);
}

$documents = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destination, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
  $relative = substr($file->getPathname(), strlen($destination) + 1);
  if ($file->isLink() || preg_match('~^(content|site|kirby|vendor|panel|api)(/|$)|(^|/)\.jobs(/|$)|\.(php\d*|phtml|phar)$~i', $relative)) {
    $errors[] = 'Private or executable file in export: ' . $relative;
  }
  if ($file->getExtension() === 'html') {
    $documents[$relative] = file_get_contents($file->getPathname());
  }
}

$home = new DOMDocument();
libxml_use_internal_errors(true);
$home->loadHTML($documents['index.html']);
$baseUrl = (new DOMXPath($home))->evaluate('string(//link[@rel="canonical"]/@href)');
$baseParts = parse_url($baseUrl);
$basePath = rtrim($baseParts['path'] ?? '', '/');

if (!$baseUrl) {
  $errors[] = 'Home page is missing its canonical URL.';
}

foreach ($documents as $relative => $html) {
  $document = new DOMDocument();
  $document->loadHTML($html);
  $xpath = new DOMXPath($document);
  foreach (['//html[@lang]', '//title', '//main[@id="main"]', '//h1', '//a[@href="#main"]'] as $query) {
    if ($xpath->query($query)->length !== 1) {
      $errors[] = $relative . ': expected exactly one ' . $query;
    }
  }
  foreach ($xpath->query('//img') as $image) {
    foreach (['width', 'height', 'alt'] as $attribute) {
      if (!$image->hasAttribute($attribute)) {
        $errors[] = $relative . ': image missing ' . $attribute;
      }
    }
  }

  foreach ($xpath->query('//*[@href or @src]') as $element) {
    foreach (['href', 'src'] as $attribute) {
      if (!$element->hasAttribute($attribute)) {
        continue;
      }
      $url = $element->getAttribute($attribute);
      if ($url === '' || str_starts_with($url, '#')) {
        continue;
      }
      $parts = parse_url($url);
      if (isset($parts['scheme']) && !in_array($parts['scheme'], ['http', 'https'], true)) {
        continue;
      }
      if (isset($parts['host']) && ($parts['host'] !== ($baseParts['host'] ?? '') || ($parts['port'] ?? null) !== ($baseParts['port'] ?? null))) {
        continue;
      }
      $path = rawurldecode($parts['path'] ?? '');
      if ($basePath !== '' && !str_starts_with($path . '/', $basePath . '/')) {
        $errors[] = $relative . ': URL escaped the deployment subpath: ' . $url;
        continue;
      }
      $path = substr($path, strlen($basePath));
      $target = $destination . '/' . ltrim($path, '/');
      if (!is_file($target) && !is_file(rtrim($target, '/') . '/index.html')) {
        $errors[] = $relative . ': broken ' . $attribute . ': ' . $url;
      }
    }
  }
}

if (!is_file($destination . '/404.html') || !is_file($destination . '/.nojekyll') || !is_file($destination . '/sitemap.xml')) {
  $errors[] = 'Missing 404.html, .nojekyll or sitemap.xml.';
}

if ($errors !== []) {
  fwrite(STDERR, implode("\n", $errors) . "\n");
  exit(1);
}

echo 'PHP syntax, blueprints, ' . count($documents) . " HTML documents, internal links and static artifact checks passed.\n";
