# Armand Demulder portfolio

Personal portfolio built with Kirby 5 and exported to static HTML for GitHub Pages.
Design direction: Studio Dumbar's black canvas, fixed edge-aligned navigation and restrained regular typography.
The homepage uses CSS snapping with an infinite native-scroll range and recycled project groups, with crossed SVG placeholders until real artwork is supplied.
Read [the design study](../STUDIO-DUMBAR-DESIGN-STUDY.md) before changing the layout or interaction.
Reference screenshots live beside that file, outside the public website directory.

## Commands

```sh
composer install
composer start
composer build
composer check
composer validate --strict --no-check-publish
composer audit --no-dev
npm ci
npx playwright install chromium
SITE_URL=http://127.0.0.1:8080 composer build
npm test
```

Local Kirby: `http://localhost:8000`.
Local Panel: `http://localhost:8000/panel`.
Static output: `dist/`, targeting `http://localhost:8080` by default.
Use `SITE_URL=https://example.com/portfolio composer build` for a deployment subpath.
Preview with `python3 -m http.server 8080 --directory dist`.

## Site map

| URL | Template | Blueprint | Notes |
| --- | --- | --- | --- |
| `/` | `home` | `pages/home` | Lists all published projects in Panel order. |
| `/projects/<slug>` | `project` | `pages/project` | Project details, cover, gallery and previous/next links. |
| `/about` | `about` | `pages/about` | Biography and background. |
| Missing URL | `error` | `pages/error` | HTTP 404 locally, exported as `404.html`. |

The homepage ID is `projects`, configured with Kirby's `home` option.
Its content lives in `content/1_projects/home.txt` so child project URLs start with `/projects/`.
Only About appears in main navigation; Armand's name links home.

## Code layout

- `content/`: versioned page text, image originals and file metadata.
- `site/blueprints/`: Panel schema; image uploads use `files/image` with required alternative text.
- `site/controllers/`: project collections and sibling navigation.
- `site/templates/`: thin page views.
- `site/snippets/layout.php`: shared document, navigation and footer.
- `site/snippets/footer.php`: inner-page update date, using the CI commit timestamp or local content/asset timestamps.
- `site/snippets/project-visual.php`: cover image or shared crossed SVG placeholder.
- `site/snippets/cover.php`: responsive image output, capped at 1600px with dimensions and alternative text.
- `assets/`: public CSS and JavaScript, with no frontend build tool.
- `assets/fonts/`: self-hosted Inter Tight Regular 400 WOFF2 and its OFL license.
- `assets/js/main.js`: bounded project-group recycling, initial scroll-range setup, idle recovery and resize preservation.
- `tests/portfolio.spec.js`: Playwright checks against the actual static site at desktop/mobile sizes.
- `scripts/build.php`: static exporter with eager media publication and thumbnail generation.
- `scripts/check.php`: PHP syntax, blueprint parsing and static artifact validation.
- `../.github/workflows/pages.yml`: checks pull requests, deploys `main` to GitHub Pages.

## Conventions

- Use two spaces for indentation.
- Never use an em dash.
- Never put emojis in comments.
- Never add an agent as commit co-author.
- Never manually edit changelogs or generated files, including `composer.lock`, `kirby/`, `vendor/`, `media/` and `dist/`.
- Regenerate the lockfile through Composer and static output through `composer build`.
- In long Markdown files, put each full sentence on its own physical line.
- Prefer quality, simplicity, robustness, scalability and long-term maintainability over development cost.
- Begin bug fixes by reproducing the end-user flow in an E2E setting.
- Fix clear UI defects, lint failures, test failures and flaky checks encountered while working.
- Escape field output with `->esc()` and attribute values with `esc($value, 'attr')`.
- Render authored rich text with `->kirbytext()`.
- Keep blueprint, template and controller names aligned; use models only when formatting logic warrants them.
- Keep collection queries in controllers and provide empty states.
- Use native links, semantic headings, a skip link, visible keyboard focus and reduced-motion support.
- Use CSS variables for colors, typography, spacing and viewport sizing.
- Use Inter Tight 400 only; keep font synthesis disabled and preserve its license.
- Home has no footer in its DOM; inner pages show the update date and Armand's sign-off.
- Preserve infinite scrolling in both directions, following [Pyotr's native-scroll approach](https://github.com/woshibide/wshbd/blob/8f87e667f27273d3a3afe610288ab2235118bc8d/src/scripts/selected-scroll.js).
- CSS owns snapping on the home main element; JavaScript maintains nearby project groups within a reserved native scroll range.
- Never write the scroll position during normal wheel/touch scrolling or at project-cycle boundaries.
- Scroll writes are limited to initial setup, viewport resize and idle recovery near the remote range limits.
- Exchange project groups and spacer heights together so surviving content keeps the same coordinates.
- Keep the DOM bounded, preserve the active touch target until touchend and hide inactive cycles from assistive technology.
- Never restore custom wheel/keyboard handlers, per-cycle teleports or JavaScript smoothing.
- Use `scroll-snap-stop: normal` so larger gestures can pass intermediate projects.
- Keep a finite, fully usable CSS-snapping fallback when JavaScript is disabled.
- Preserve native touch, keyboard, focus, resize and reduced-motion behavior.

## Hosting and publishing

The Git repository root is the parent directory, containing this `www/` directory and the design research.
GitHub reads the workflow from the repository's root `.github/workflows/` directory.
Workflow shell commands run in `www/`, and only `www/dist/` is published.
Enable Settings > Pages > Build and deployment > Source > GitHub Actions after creating the repository.
The workflow reads the real site URL from `actions/configure-pages`, including repository subpaths and configured custom domains.
Pull requests build and check with a test subpath but cannot deploy.
Only `main` can deploy, including manual workflow runs.
CI runs desktop/mobile browser tests against a local static build before building the actual deployment URL.
Node 24 is used for browser tests only; PHP development and static export do not need Node.
The production build receives `SITE_UPDATED_AT` from the current Git commit timestamp.
Do not hardcode the footer date.

GitHub Pages runs no PHP, Panel, API, forms or background thumbnail jobs.
Edit through local Kirby, publish changes, then commit `content/`, uploaded originals and metadata to trigger a rebuild.
Draft pages and unsaved changes are not published.
Unlisted pages are public Kirby pages and are exported.
Panel accounts, sessions, caches and source code never belong in the static artifact.
The exporter uses a fresh media directory and publishes referenced media only, avoiding stale local draft media.
Add static support and checks before introducing pagination, custom routes, feeds or content representations.
Do not add runtime forms without a separately chosen service.

Production configuration disables debug and Panel installation.
Localhost configuration enables both only for the PHP development server.
Keep that server local; it is not a production web server.
The GitHub remote is `https://github.com/woshibide/armand.git`, with `main` as the publishing branch.

## Plugins and agent tooling

See [PLUGINS.md](PLUGINS.md) for dependency decisions and interactions.
Kirby MCP is deferred until a Kirby 5 compatible release supports a patched SDK.
Do not restore the vulnerable version range described there.
Use official Kirby documentation, local core source and direct Kirby APIs for current verification.

## Verify a change

1. Reproduce the relevant user flow against the running Kirby site.
2. Check home, all project URLs, about, a missing URL and Panel.
3. For schema changes, verify real Panel API saves and publishes with a temporary local account; clean up test data afterward.
4. For media changes, check resizing, dimensions, alternative text and exported files with a temporary image.
5. Run `composer build` and `composer check`.
   For interaction or layout changes, build with `SITE_URL=http://127.0.0.1:8080`, then run `npm test`.
6. Repeat the export with `SITE_URL=https://example.com/portfolio` when URL handling changes.
7. Check the actual static site over HTTP, including deep project links.
8. Run Composer validation and audit after dependency changes.
9. Inspect the dev server log for new errors and remove temporary accounts, content, media and cache files.
