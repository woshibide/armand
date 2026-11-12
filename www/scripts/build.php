<?php

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\FileVersion;
use Kirby\Filesystem\Asset;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;

require dirname(__DIR__) . '/kirby/bootstrap.php';

$root = dirname(__DIR__);
$destination = $root . '/dist';
$baseUrl = rtrim($argv[1] ?? (getenv('SITE_URL') ?: 'http://localhost:8080'), '/');
$parts = parse_url($baseUrl);

if (
  filter_var($baseUrl, FILTER_VALIDATE_URL) === false ||
  !in_array($parts['scheme'] ?? '', ['http', 'https'], true) ||
  isset($parts['user']) || isset($parts['pass']) ||
  isset($parts['query']) || isset($parts['fragment']) ||
  preg_match('~(?:^|/)\.\.(?:/|$)~', rawurldecode($parts['path'] ?? ''))
) {
  fwrite(STDERR, "SITE_URL must be an absolute HTTP(S) URL without credentials, query or fragment.\n");
  exit(1);
}

if (is_link($destination)) {
  throw new RuntimeException('Refusing to replace a symlink at dist.');
}

Dir::remove($destination);
Dir::make($destination);

$props = [
  'roots' => ['index' => $root, 'media' => $destination . '/media'],
  'urls' => ['index' => $baseUrl],
  'options' => [
    'url' => $baseUrl,
    'debug' => false,
    'panel' => false,
    'api' => false,
    'cache.pages.active' => false,
  ],
  // Publish only media actually used by rendered public pages.
  // Generate thumbnails now because GitHub Pages cannot process Kirby jobs.
  'components' => [
    'file::url' => function (App $kirby, File $file): string {
      $file->publish();
      return $file->mediaUrl();
    },
    'file::version' => function (App $kirby, File|Asset $file, array $options): File|Asset|FileVersion {
      $version = ($kirby->nativeComponent('file::version'))($kirby, $file, $options);
      if ($version instanceof FileVersion && !$version->exists()) {
        $version->save();
      }
      return $version;
    },
  ],
];

$app = new App($props);
$routes = [];
$urls = [];

// The default index contains published pages, including unlisted pages, never drafts.
foreach ($app->site()->index() as $page) {
  if ($page->isErrorPage()) {
    continue;
  }
  $route = $page->isHomePage() ? '' : $page->uri();
  $routes[$route] = $route === '' ? 'index.html' : $route . '/index.html';
  $urls[] = $page->url();
}

foreach ($routes as $route => $file) {
  // Each render gets a fresh request so active navigation and page context are correct.
  $app = new App([...$props, 'request' => ['url' => $baseUrl . '/' . $route]]);
  $response = $app->render($route);
  if ($response?->code() !== 200) {
    throw new RuntimeException('Could not render /' . $route . ': HTTP ' . ($response?->code() ?? 'none'));
  }
  F::write($destination . '/' . $file, $response->body());
  echo 'Built /' . $route . "\n";
}

$app = new App([...$props, 'request' => ['url' => $baseUrl . '/__static_404__']]);
$response = $app->render('__static_404__');
if ($response?->code() !== 404) {
  throw new RuntimeException('The error page must return HTTP 404.');
}
F::write($destination . '/404.html', $response->body());
Dir::copy($root . '/assets', $destination . '/assets');

// Kirby may leave empty thumbnail-job directories; none belong in a Pages artifact.
$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($destination, FilesystemIterator::SKIP_DOTS),
  RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $entry) {
  if (str_starts_with($entry->getBasename(), '.')) {
    $entry->isDir() ? Dir::remove($entry->getPathname()) : F::remove($entry->getPathname());
  }
}

$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
foreach ($urls as $url) {
  $xml->addChild('url')->addChild('loc', htmlspecialchars($url, ENT_XML1, 'UTF-8'));
}
F::write($destination . '/sitemap.xml', $xml->asXML());
F::write($destination . '/robots.txt', "User-agent: *\nAllow: /\n\nSitemap: " . $baseUrl . "/sitemap.xml\n");
F::write($destination . '/.nojekyll', '');

echo 'Exported ' . count($routes) . ' pages and 404.html to dist for ' . $baseUrl . ".\n";
