# Armand Demulder

Personal portfolio powered by Kirby 5, published as a static website on GitHub Pages.
The homepage loops through three full-screen placeholder projects, using CSS scroll snapping and crossed SVG artwork.
Studio Dumbar's layout informs the black canvas, fixed navigation and typography, using locally hosted Inter Tight Regular.
About and project pages share the same visual system and a dated footer; home has no footer.
No analytics are installed.

## Local development

Requirements: PHP 8.2, 8.3, 8.4 or 8.5 with Kirby's required extensions, Composer 2 and Git.
Image processing needs GD; CI also enables intl, mbstring, DOM, fileinfo and SQLite support.
Node 24 is needed only for optional local browser tests and CI.
Kirby is commercial software; review its [license](https://getkirby.com/license) before production use.

```sh
cd www
composer install
composer start
```

Open [localhost:8000](http://localhost:8000).
Use [localhost:8000/panel](http://localhost:8000/panel) to create your local account and edit content.
Accounts stay local and are ignored by Git.
Stop the development server with Ctrl+C.

## Content

| Page | URL | Content |
| --- | --- | --- |
| Home | `/` | `content/1_projects/home.txt` |
| Project one | `/projects/project-one` | `content/1_projects/1_project-one/project.txt` |
| Project two | `/projects/project-two` | `content/1_projects/2_project-two/project.txt` |
| Project three | `/projects/project-three` | `content/1_projects/3_project-three/project.txt` |
| About | `/about` | `content/2_about/about.txt` |

In the Panel, open Home to add, reorder or edit projects.
Projects support an introduction, story, discipline, year, role, cover and gallery.
Upload images with descriptive alternative text.
Add an optional contact email under Site > Settings to show an email link in the footer.
The About text uses Armand's requested joke; no email address has been invented.
Global CSS tokens live at the top of `assets/css/main.css`.
The font and its license live in `assets/fonts/`.

## Static build

```sh
composer build
composer check
python3 -m http.server 8080 --directory dist
```

Open [localhost:8080](http://localhost:8080).
The exporter renders published Kirby pages, generates required thumbnails and writes HTML, assets, referenced media, `404.html`, `sitemap.xml`, `robots.txt` and `.nojekyll` to `dist/`.
Drafts, source code, accounts and Panel are excluded.
The checks validate PHP syntax, blueprints, HTML landmarks, image attributes, internal links and artifact contents.

For a different domain or repository subpath, pass the full site URL:

```sh
SITE_URL=https://example.github.io/portfolio composer build
composer check
```

The static output has no PHP runtime, Panel or form processing.
Unlisted Kirby pages are public and exported; drafts are excluded.
The homepage uses CSS snapping and a reserved native scroll range, with JavaScript recycling nearby project groups for infinite forward/backward scrolling.
The approach follows [Pyotr's selected-projects page](https://its-pyotr.com/selected/) and [its scroll implementation](https://github.com/woshibide/wshbd/blob/8f87e667f27273d3a3afe610288ab2235118bc8d/src/scripts/selected-scroll.js).
Ordinary wheel/touch scrolling never repositions the scroller; only initial setup, viewport resize and rare idle range recovery do so.
With JavaScript disabled, the finite project list still snaps and links to project pages.
The header contains About and Armand's name as the home link.
Custom dynamic routes, pagination and content representations need explicit export support before use.

## GitHub Pages

The repository root contains `www/`, the design research and `.github/workflows/pages.yml`.
The workflow runs its commands in `www/` and publishes only `www/dist/`, with `main` as the default branch.

1. Push the repository root to [woshibide/armand](https://github.com/woshibide/armand).
2. Open Settings > Pages > Build and deployment, then select GitHub Actions as Source.
3. Run the workflow manually from `main`, or push a commit to `main`.

The workflow installs locked dependencies, validates Composer, audits dependencies, runs desktop/mobile browser tests, builds the site, checks the artifact and deploys through GitHub's Pages actions.
The deployment URL comes from GitHub, supporting both repository subpaths and configured custom domains.
Pull requests build and check without deploying.
Only `main` can deploy.

To publish edits, save and publish them in the local Panel, then commit and push `content/`, including uploaded image originals and their metadata files.
GitHub Pages cannot host Kirby's Panel.
The repository remote is `https://github.com/woshibide/armand.git`.

The footer date uses the current Git commit timestamp during deployment, formatted as `DD.MM.YYYY` in Europe/Amsterdam.
Local rendering falls back to published content and asset modification times.
Set `SITE_UPDATED_AT` to an ISO timestamp when building outside the workflow if a fixed release date is needed.

## fortrabbit

The Kirby app is [armand](https://dash.fortrabbit.com/environments/en-j3yorp), environment `en-j3yorp` in Ireland (`eu-w1a`).
Its main domain is [armand.frbit.cloud](https://armand.frbit.cloud).
The environment uses PHP 8.5, Kirby 5 and XS PHP, storage, traffic and backups, with database and jobs disabled.
Autoscaling is disabled.
The app was created as an unbilled trial ending 19 November 2026, with no payment method attached.
The verified price after the trial is EUR 2.50 per month; continuation requires adding a payment method in the dashboard.

Pushes to `main` deploy code from `www/`.
The build runs `cd www && composer install --no-dev --prefer-dist --no-interaction --no-ansi --no-progress` from the checkout root.
The deployed directory becomes the web root, and `rm -rf site/cache/*/pages` runs afterward.
Deployment settings stay in the fortrabbit dashboard.

Kirby's deployment template preserves `content/`, `media/`, `site/accounts/` and `site/sessions/`.
The initial placeholder content is copied separately because Git deployments exclude it.
To publish later content changes, first review a dry run from this `www/` directory:

```sh
rsync -avzn --exclude='.DS_Store' ./content/ en-j3yorp@ssh.eu-w1a.frbit.app:./content/
rsync -avz --exclude='.DS_Store' ./content/ en-j3yorp@ssh.eu-w1a.frbit.app:./content/
```

Keep published content in Git too, so GitHub Pages builds the same projects.
Production debug and Panel installation are disabled.
Build and check scripts accept CLI execution only, and Apache blocks direct access to build tools, dependencies and generated previews.

## Browser checks

```sh
npm ci
npx playwright install chromium
SITE_URL=http://127.0.0.1:8080 composer build
npm test
```

Playwright starts a local static server and checks desktop/mobile layouts, repeated loop cycles, continuous momentum, blank-frame prevention, bounded DOM, zero scroll writes during gestures, idle recovery, native keyboard navigation, touch, reduced motion, resize, links, footer content and JavaScript-disabled behavior.
The site still ships plain CSS and JavaScript, with no frontend bundler or browser-test dependencies in `dist/`.

## Project notes

Read [AGENTS.md](AGENTS.md) for architecture and verification conventions and [PLUGINS.md](PLUGINS.md) for dependency decisions.
The reference research and implementation brief live one level above this directory in `STUDIO-DUMBAR-DESIGN-STUDY.md`.
Kirby MCP is deferred because its Kirby 5 compatible package currently requires an SDK version with a security advisory.
See [Kirby's documentation](https://getkirby.com/docs) and [GitHub Pages workflows](https://docs.github.com/en/pages/getting-started-with-github-pages/using-custom-workflows-with-github-pages) for platform details.
