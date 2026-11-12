# Kirby project playbook

How to set up a new Kirby website from zero to a deploy-ready structure, without any visual design.
Distilled from a real project; written for coding agents (Claude Code or similar) working with a human lead.

Follow the phases in order.
Each phase ends with a verification step; do not move on until it passes.

## Principles

- Prepare the environment only.
  Do not design: no colours, fonts, layout or decorative CSS until there is a design brief.
- Prefer the smallest technically complete solution.
  Few moving parts, boring tools, explicit code.
- Verify everything end to end against the running site (real HTTP requests, real Panel API), not only by reading code.
- Use temporary test content to verify, then delete it, including generated thumbnails and cache files.
- Never act on outward-facing things for the user (submitting their email to a vendor, killing another project's server, deploying) without their go-ahead.
- Report decisions you made on the user's behalf, so they can overturn them.
- Keep one canonical place for each kind of knowledge: `AGENTS.md` for the project, `PLUGINS.md` for plugins.

## Phase 0: Check the toolchain

```sh
php -v; composer --version; node -v
php -m | grep -iE '^(intl|gd|pdo_sqlite|pdo_mysql)$'
```

- Find the newest stable Kirby major and version: `composer show getkirby/cms --all | grep versions`.
  Ignore alpha, beta and RC releases unless the user asks for them.
- Check sibling folders (e.g. `../0_input`, `../1_research`) for briefs or prototypes that give context.

## Phase 1: Scaffold from Plainkit

```sh
composer create-project getkirby/plainkit:<latest-stable> . --no-interaction
```

Then make it the project's own:

1. `composer.json`: set `name` (`<org>/<project>`), `description`, `"license": "proprietary"`, remove the Plainkit `authors`/`keywords`/`support`, and pin `getkirby/cms` to the current minor (e.g. `^5.6`).
   Re-encode with 2-space indentation to match `.editorconfig`, then `composer update --lock` and `composer validate --no-check-publish`.
2. `.gitignore`: add `/kirby` and `/vendor` (both rebuilt from `composer.lock`).
3. `site/config/config.php`: production defaults (`'debug' => false`).
   `site/config/config.localhost.php`: local overrides (`'debug' => true`).
4. `site/snippets/layout.php`: the whole HTML document (`lang`, charset, viewport, title, one CSS file, one JS module, `<main id="main">`), rendered by templates with `<?php snippet('layout', slots: true) ?> … <?php endsnippet() ?>`.
5. `site/templates/default.php`: title and text inside the layout.
6. `assets/css/main.css`: a reset only.
   Include: `box-sizing`, `body { margin: 0 }`, responsive media, `font: inherit` on form controls, a visible `:focus-visible` outline, and a `prefers-reduced-motion` block.
7. `assets/js/main.js`: empty ES module.
8. Rewrite `README.md` for the project: requirements, `composer install`, `composer start`, where to read more.

Indentation is 2 spaces everywhere (Plainkit's `.editorconfig`); do not write tabs.

**Verify:** `composer start`, then `curl` the home page, a 404 URL and `/panel` (redirects to `/panel/installation` on a fresh install).

If port 8000 is taken, find the owner with `lsof -nP -iTCP:8000 -sTCP:LISTEN` and its working directory with `lsof -a -p <pid> -d cwd`.
Ask the user before stopping another project's server.
Note: `composer start` binds `localhost` (IPv6 `::1` on macOS); `127.0.0.1:8000` will not reach it.

## Phase 2: Web interface guidelines pass

```sh
npx -y skills use "https://github.com/vercel-labs/agent-skills" --skill "web-design-guidelines" > /tmp/skill.txt
curl -sfL https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md -o /tmp/guidelines.md
```

Review the layout, templates and CSS against every rule, report findings as `file:line - issue`, and fix only the ones that are not design decisions:

- Skip link (`<a class="skip-link" href="#main">`) that is visually hidden until focused.
- A shared `.visually-hidden` utility (also used for honeypot fields).
- `overflow-wrap: break-word` on `body`; `text-wrap: balance` on headings; `text-wrap: pretty` on `p, li`.
- `touch-action: manipulation` on links, buttons and form controls.
- Images without dimensions: set `'kirbytext' => ['image' => ['width' => 'auto', 'height' => 'auto']]` in `config.php`, so `(image:)` tags get `width` and `height` from the file.

Defer, and say so: `theme-color`, `color-scheme`, `-webkit-tap-highlight-color` (they need a palette).

## Phase 3: Kirby MCP and skills

Match the MCP major to the Kirby major.
Check the README of https://github.com/bnomei/kirby-mcp for the line that supports your Kirby version (for Kirby 5 it was `^1.15`; `6.x` requires Kirby 6 and conflicts with Kirby 5).

```sh
composer require --dev bnomei/kirby-mcp:"^1.15"
vendor/bin/kirby-mcp install
claude mcp add -s project kirby -- "$PWD/vendor/bin/kirby-mcp" "--project=$PWD"
```

- Add a Composer script `"mcp": "kirby-mcp install"` so a fresh checkout can regenerate the runtime.
- Gitignore what the installer writes: `/.kirby-mcp`, `/site/commands/mcp`, and the plugin adapter in `site/plugins/kirby-mcp`.
  This is critical: the adapter calls a class from the dev-only package, so deploying it with `composer install --no-dev` crashes every page.
- The dev server (`kirby/router.php`) serves every file under the project root, including `content/`, `site/` and `.kirby-mcp/`.
  That is acceptable locally only; on Apache `.htaccess` blocks those paths.

Install the skills from the same tag as the package, so the skills describe your Kirby version:

```sh
git ls-remote --tags https://github.com/bnomei/kirby-mcp | grep <version>
npx -y skills add "https://github.com/bnomei/kirby-mcp/tree/<tag>" --list
npx -y skills add "https://github.com/bnomei/kirby-mcp/tree/<tag>" \
  -s kirby-scaffold-page-type -s kirby-panel-and-blueprints \
  -s kirby-collections-and-navigation -s kirby-forms-and-frontend-actions \
  -a claude-code --copy -y
```

Pick only the skills relevant to the task.
This writes `.claude/skills/` and `skills-lock.json`; keep both in the repository.
Confirm the copies match the package: `diff -rq .claude/skills vendor/bnomei/kirby-mcp/skills`.

The MCP tools only load after the agent restarts.
Until then, drive the server over stdio with a small client (below) so you can still follow the skills' workflows.
Call `kirby_init` first in every session; other tools refuse to run until you do.
The bundled knowledge base is also readable directly in `vendor/bnomei/kirby-mcp/kb/`.

```python
# mcp_call.py: python3 -I mcp_call.py '[["tools/call",{"name":"kirby_init","arguments":{}}], ...]'
import json, subprocess, sys
ROOT = "/absolute/path/to/project"
calls = json.loads(sys.argv[1])
proc = subprocess.Popen([f"{ROOT}/vendor/bin/kirby-mcp", f"--project={ROOT}"],
    stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True, cwd=ROOT)
def send(m): proc.stdin.write(json.dumps(m) + "\n"); proc.stdin.flush()
def recv(i):
    while True:
        m = json.loads(proc.stdout.readline())
        if m.get("id") == i: return m
send({"jsonrpc": "2.0", "id": 0, "method": "initialize", "params": {
    "protocolVersion": "2025-06-18", "capabilities": {}, "clientInfo": {"name": "probe", "version": "1"}}})
recv(0); send({"jsonrpc": "2.0", "method": "notifications/initialized"})
for i, (method, params) in enumerate(calls, 1):
    send({"jsonrpc": "2.0", "id": i, "method": method, "params": params})
    r = recv(i).get("result", {})
    for c in r.get("content", r.get("contents", [])): print(c.get("text", c))
proc.terminate()
```

Useful calls: `kirby_init`, `kirby_blueprints_index`, `kirby_blueprint_read {"id":"pages/<type>"}`, `kirby_render_page {"id":"<page-id>","noCache":true}`, `resources/read {"uri":"kirby://roots"}`.

## Phase 4: Information architecture

Decide the page list with the user, then write it down as a site map table (URL, template, blueprint, notes).
Typical shapes:

| Kind | Pattern |
| --- | --- |
| Simple text pages (about, privacy, home before design) | Reuse the `default` template and blueprint until they need different fields. |
| Collection with detail pages (blog, shop, team, projects) | Parent type + child type (e.g. `blog` + `post`), listing logic in a controller. |
| Single structured page (press, contact) | Own type with fields, structure lists and a files section. |
| Utility pages (newsletter endpoint) | Own type, unlisted folder (no number). |

Content folders: `content/<n>_<slug>/<template>.txt`.
Numbered folders are listed, so the number sets the main navigation order; unnumbered folders are unlisted (linked from the footer or nowhere).

Conventions that worked:

- **Blueprints** (`site/blueprints/pages/<type>.yml`): main column for text, sidebar for meta fields and a files section.
  Collections use one `pages` section with `template:` and `sortBy:`.
  Dated children use `num: date`; alphabetical children use `num: zero` and sort by title in the controller.
- **File blueprint** `site/blueprints/files/image.yml` with an `alt` field, and `uploads: image` on every files field and section, so every image has alt text.
- **Controllers** (`site/controllers/<type>.php`) own collection logic and return named collections plus `pagination`: `$page->children()->listed()->sortBy('date', 'desc')->paginate(10)`.
- **Templates** stay thin and always render an empty state (`No posts yet.`).
- **Snippets** for anything shared: `cover` (resized to at most 1600px, with `width`, `height`, `alt`), `date` (a `<time>` for any date field: `snippet('date', ['date' => $page->date()])`), `pagination` (prev/next with `rel`).
- **Models** for formatting logic, e.g. `ProductPage::formattedPrice()` with `NumberFormatter`, and a class constant for the currency.
  Do not name a model method like a field (`price()`), or it shadows the field.
- **Navigation** in the layout: listed top-level pages, `aria-current="page"` on the active page and `aria-current="true"` on its section.
- **Escaping:** `->esc()` on fields, `esc($string, 'attr')` in attributes, `Html::email()` for addresses.
- Use the MCP skill `kirby-scaffold-page-type` for each new type: check for name collisions first, keep template, blueprint, controller and model names identical.

**Verify:**

1. `php -l` on every PHP file.
2. `curl` every URL from the site map; check status codes and the `<main>` output.
3. Add temporary children (enough to trigger pagination, e.g. 11 posts), an image with alt text, a relation (e.g. product to artist), and check listings, detail pages, `page:2`, dimensions and escaping (put `<b>` and `"` in test values).
4. `kirby_blueprints_index` and `kirby_render_page` through MCP.
5. Delete the test content, `media/pages/` and any cache folders.

## Phase 5: A site-wide signup form

For a newsletter (or any) form that sits in the footer of every page:

- Post it to an unlisted page (e.g. `/newsletter`) whose controller handles POST, and which also works as a standalone signup page.
- Validate with `V::email()`, return `email`, `error` and `done` to the snippet, and render errors inline with `aria-invalid`, `aria-describedby` and an `aria-live="polite"` message.
- Spam guard: a honeypot field inside `.visually-hidden aria-hidden="true"`, `tabindex="-1"`, `autocomplete="off"`. If it is filled, pretend success.
- No CSRF token in a footer form: a token starts a session on every page, which disables page caching (and Staticache) site-wide.
  The request changes no account state, and the newsletter provider's double opt-in is the real safeguard.
  Document this decision in the controller and in `AGENTS.md`.
- Until a provider is chosen, leave one clearly marked `TODO` where the address would be sent.
- Input attributes: `type="email"`, `name="email"`, `autocomplete="email"`, `spellcheck="false"`, `required`.

**Verify** with POSTs: invalid address (error shown, value escaped), empty, valid (confirmation), honeypot filled (confirmation, nothing stored), and `curl -sI` a normal page to confirm there is no `Set-Cookie`.

## Phase 6: Documentation for agents

- `AGENTS.md`: what the site is, status (structure only, no design), commands, site map table, code layout, conventions, special decisions (forms, caching), plugins pointer, hosting, Kirby MCP rules, UI rules, and a "verify a change" checklist.
- `CLAUDE.md` containing only `@AGENTS.md`, so Claude Code loads the same file.
- `PLUGINS.md` (Phase 7).
- Write one sentence per line in long Markdown files.

## Phase 7: Plugins

### Research before installing

For each requested plugin:

1. Find the Composer package: `curl -s "https://packagist.org/search.json?q=<vendor>+<name>"`.
2. Read its latest stable version and constraints from Packagist:

   ```python
   # pkgs.py: python3 -I pkgs.py vendor/a vendor/b
   import json, sys, urllib.request
   for name in sys.argv[1:]:
       vs = json.load(urllib.request.urlopen(f"https://repo.packagist.org/p2/{name}.json"))["packages"][name]
       v = next((x for x in vs if not any(t in x["version"] for t in ("dev", "alpha", "beta", "RC"))), vs[0])
       r = v.get("require", {})
       print(name, v["version"], v.get("time", "")[:10], "php=", r.get("php"), "cms=", r.get("getkirby/cms"),
             "conflict=", v.get("conflict", {}).get("getkirby/cms"))
   ```

3. Confirm Kirby support on the directory page:
   `curl -sL https://plugins.getkirby.com/<vendor>/<name> | sed 's/<[^>]*>/ /g' | tr -s ' \n' ' ' | grep -oE 'Kirby support( Kirby [0-9])+'`.
4. Plugins without a public package (e.g. Kirby Vault's free plugins) need the user to claim them with their own email; stop and tell the user.

Install everything in one `composer require` with explicit `^x.y` constraints, then read every plugin's README in `site/plugins/<name>/`.
Raise the project's PHP constraint if a plugin needs a newer PHP than Kirby.
Gitignore `/site/plugins/*` (Composer rebuilds it); whitelist a folder only for hand-written local plugins.

### Check interactions

Ask of every plugin: does it change output for logged-in users, use sessions, cookies or CSRF, register snippets or routes with common names, or store data on disk?
Write the answers in `PLUGINS.md` (a table of plugin, package, version, status, purpose, plus an "interactions" section and an "adding a plugin" checklist).

### Notes on specific plugins

**getkirby/staticache** (static HTML served by the web server)

- Config: `'cache' => ['pages' => ['active' => true, 'type' => 'static']]` in `config.php`; switch it off in `config.localhost.php` (the dev server cannot serve the files, and stale files confuse development).
- Apache: add the rules after the `RewriteBase` comment in `.htaccess`, guarded so logged-in users and form posts always reach Kirby:

  ```apache
  RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$
  RewriteCond %{HTTP_COOKIE} !kirby_session
  RewriteCond %{DOCUMENT_ROOT}/site/cache/%{SERVER_NAME}/pages/%{REQUEST_URI}/index.html -f
  RewriteRule ^(.*) %{DOCUMENT_ROOT}/site/cache/%{SERVER_NAME}/pages/%{REQUEST_URI}/index.html [END]

  RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$
  RewriteCond %{HTTP_COOKIE} !kirby_session
  RewriteCond %{DOCUMENT_ROOT}/site/cache/%{SERVER_NAME}/pages/%{REQUEST_URI} -f
  RewriteRule ^(.*) %{DOCUMENT_ROOT}/site/cache/%{SERVER_NAME}/pages/%{REQUEST_URI} [END]
  ```

- Kirby never caches private responses (requests whose auth or cookies the page used), POST responses, or URLs with params (`/blog/page:2`) or query strings.
- Any Panel change that actually changes content flushes the whole cache.
- It caches on first visit; it does not pre-generate the site.

**pechente/kirby-admin-bar**

```php
'ready' => fn ($kirby) => ['pechente.kirby-admin-bar' => ['active' => $kirby->user() !== null]],
```

**moinframe/kirby-loop** (client feedback), switchable in the Panel

- Add a Settings tab to `site/blueprints/site.yml` with a `toggle` field `loop` (`default: false`).
- `'moinframe.loop.enabled' => fn () => site()->content()->get('loop')->toBool()`.
- Keep `moinframe.loop.public` off so only logged-in users see it (and it never lands in a static file).
- Needs PHP 8.3+ and SQLite; comments live in `site/logs/loop/comments.sqlite`, so gitignore `/site/logs` and keep it across deploys.

**sylvainjule/matomo** (self-hosted, cookieless)

- Read settings from environment variables, so the plugin stays inactive locally and secrets stay out of Git:

  ```php
  'sylvainjule.matomo.url'            => getenv('MATOMO_URL') ?: false,
  'sylvainjule.matomo.id'             => getenv('MATOMO_SITE_ID') ?: false,
  'sylvainjule.matomo.token'          => getenv('MATOMO_TOKEN') ?: false,
  'sylvainjule.matomo.disableCookies' => true,
  'sylvainjule.matomo.area'           => getenv('MATOMO_TOKEN') !== false,
  ```

- `<?php snippet('matomo') ?>` before `</body>`; it renders `<!-- Kirby Matomo -->` while inactive.
- Matomo itself needs PHP and MySQL, as a separate app with its own database.

**bnomei/kirby3-htmlhead**

- Use the global `htmlhead()` helper.
  `$page->htmlhead()` is declared to return a string, so chaining on it fatals.
- Its snippets print values unescaped: pass already escaped strings, e.g. `->title($page->title()->esc() . ' | ' . $site->title()->esc())`.
- It registers a snippet named `matomo` that clashes with the Matomo plugin's; Matomo's wins because plugin folders load alphabetically. Check the rendered output after updates.
- A minimal chain: `recommended_minimum()`, `title(...)`, `link_css([...])`, `script_js([['src' => 'assets/js/main.js', 'type' => 'module']])`, `meta_robots()` (noindex while debug is on), `link_canonical([$page->url()])`.

**medienbaecker/kirby-tiptap**

- Switch rich text fields from `type: textarea` to `type: tiptap` with `uploads: image`, and templates from `->kirbytext()` to `->tiptapText()`.
- Existing content needs `kirby tiptap:convert` (dry run first); fresh projects need nothing.

**mzur/kirby-uniform**

- Always requires a CSRF token and uses session flash data, so any page with a Uniform form cannot be statically cached.
- Fine for single-page forms (contact); wrong for a footer form on every page (use Phase 5 instead).
- Its email action needs a mail transport; some hosts have no `sendmail`.

### Panel accounts

Create a local admin through Kirby itself, never by hand-writing account files:

```sh
php -r 'require "kirby/bootstrap.php"; $k = new Kirby(); $k->impersonate("kirby");
$k->users()->create(["email" => "<email>", "password" => "<password>", "role" => "admin", "language" => "en"]);'
```

If it already exists, verify instead with `$k->user("<email>")->validatePassword("<password>")`.
`site/accounts/` is gitignored; treat test credentials as local only.

## Phase 8: Hosting on fortrabbit

Docs: https://docs.fortrabbit.com/guides/kirby
Re-check them before each project; they change.

- Apache with `.htaccess` support; set the document root to the repository root.
- Deploy with Git.
  The build runs `composer install --no-dev --prefer-dist --no-interaction --no-ansi --no-progress`, so dev-only tooling must be fully gitignored (see Phase 3).
- Post-deploy command: `rm -rf site/cache/*/pages` (also clears Staticache files).
- Add to the environment's excluded paths, so they survive deploys: `/content`, `/media` (from fortrabbit's guide), plus `/site/accounts`, `/site/sessions`, `/site/logs`.
- fortrabbit's guide keeps `content/` out of Git and syncs it with rsync; agree this with the user.
- Environment variables: `APP_DEBUG=false`, `APP_MODE=production`, and any secrets (Matomo, SMTP).
- Choose PHP 8.3+ in the dashboard; confirm `intl` and `pdo_sqlite` with `phpinfo()`.
- Panel accounts are not deployed: create the first one on the server with `panel.install` enabled only for that moment.
- No `sendmail`: configure SMTP in the `email` option for any form that sends mail.
- MySQL is available per environment, so a self-hosted Matomo gets its own environment and database.

Document all of this in the project's `AGENTS.md` hosting section.

## Verification toolbox

### Every route

```sh
for u in / about blog nope; do printf '%s /%s\n' "$(curl -s -o /dev/null -w '%{http_code}' localhost:8000/$u)" "$u"; done
```

### Panel API as a logged-in editor

The Panel's CSRF token is embedded in its HTML; fetch it with `-L` after login (the login page redirects once you are logged in).
Kirby 5 saves Panel edits in two steps: `changes/save`, then `changes/publish`.

```sh
BASE=http://localhost:8000; JAR=$(mktemp)
csrf() { curl -sL -b "$JAR" -c "$JAR" "$BASE/panel/login" | grep -oE '"csrf":"[^"]+"' | head -1 | cut -d'"' -f4; }
TOKEN=$(csrf)
curl -s -b "$JAR" -c "$JAR" -H "x-csrf: $TOKEN" -H 'content-type: application/json' \
  -d '{"email":"<email>","password":"<password>","long":false}' "$BASE/api/auth/login"
TOKEN=$(csrf)
for step in save publish; do
  curl -s -b "$JAR" -H "x-csrf: $TOKEN" -H 'content-type: application/json' -X POST \
    -d '{"loop":"true"}' "$BASE/api/site/changes/$step"
done
curl -s -b "$JAR" "$BASE/"   # logged-in view
curl -s "$BASE/"             # anonymous view
```

Use it to prove that editor-only UI (admin bar, feedback widgets) appears for the logged-in request and never for the anonymous one, and that toggles write to `content/site.txt`.

### Staticache

1. Switch `active` on in `config.localhost.php` for the test.
2. Request pages anonymously, a POST, and a `page:2` URL; list `site/cache/localhost_8000/pages/`.
3. Grep the static files for editor UI or CSRF tokens (expect none).
4. Visit a page while logged in (expect no file written).
5. Make a real content change through the Panel API (expect the folder to empty).
   An unchanged value does not flush.
6. Switch `active` off again and delete `site/cache/localhost_8000`.

### `.htaccess` rules on a real Apache (macOS)

macOS ships Apache in `/usr/sbin/httpd`.
A throwaway config without PHP is enough: static hits return the file, misses return the raw `index.php` source.

```apache
ServerRoot "/usr"
Listen 127.0.0.1:8089
PidFile "/tmp/apache-test/httpd.pid"
ErrorLog "/tmp/apache-test/error.log"
LoadModule mpm_prefork_module libexec/apache2/mod_mpm_prefork.so
LoadModule authz_core_module libexec/apache2/mod_authz_core.so
LoadModule unixd_module libexec/apache2/mod_unixd.so
LoadModule mime_module libexec/apache2/mod_mime.so
LoadModule dir_module libexec/apache2/mod_dir.so
LoadModule rewrite_module libexec/apache2/mod_rewrite.so
LoadModule setenvif_module libexec/apache2/mod_setenvif.so
LoadModule headers_module libexec/apache2/mod_headers.so
LoadModule filter_module libexec/apache2/mod_filter.so
LoadModule deflate_module libexec/apache2/mod_deflate.so
TypesConfig /private/etc/apache2/mime.types
ServerName www.example.test
UseCanonicalName On
DocumentRoot "/absolute/path/to/project"
DirectoryIndex index.php
<Directory "/absolute/path/to/project">
  AllowOverride All
  Require all granted
</Directory>
```

Put fake files in `site/cache/www.example.test/pages/`, run `httpd -f <conf> -t`, then `-k start`, test anonymous GET, GET with `Cookie: kirby_session=x`, POST, an uncached URL and `/content/site.txt`, then `-k stop` and delete the fake files.

### Browser

Use Chrome DevTools MCP to check the Panel and frontend visually when it is available.
If its profile is locked by another session, fall back to the HTTP checks above and say that the visual check is still open.

## Gotchas collected

- Kirby MCP `6.x` conflicts with Kirby 5; the bundled skills differ per tag as well.
- Generated MCP runtime files plus a `--no-dev` deploy crash every page.
- `$page->htmlhead()` returns a string; use `htmlhead()`.
- Two plugins can register the same snippet name; the alphabetically later folder wins.
- CSRF tokens (`csrf()`, `csrf_field()`, Uniform) start sessions and disable caching for that page.
- Apache serves static files before Kirby can check the user; guard the rules with the session cookie and request method.
- `composer start` listens on `localhost` (IPv6) only.
- `resize(1600)` never upscales; read `width()` and `height()` from the returned version, not the original.
- PHP 8.5 shows a deprecation notice from `kirby/src/Http/Uri.php` with Kirby 5.6.1; it is core, not project code.
- Check the dev server log for fatals after every change; old stack traces stay in the log, so compare timestamps.

## Final report to the user

- Lead with what works now and the next action, with the URL.
- One table of what was installed or created, with status.
- List what was verified and how, and what could not be verified.
- List the decisions made on the user's behalf.
- Raise blockers that need the user (accounts, emails, hosting choices) as one short question at the end.
