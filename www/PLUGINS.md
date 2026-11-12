# Plugins and tooling

| Dependency | Version | Status | Purpose |
| --- | --- | --- | --- |
| `getkirby/cms` | 5.6.3, constrained to `~5.6.3` | Installed | CMS and local Panel. |
| `getkirby/composer-installer` | Locked by Composer | Installed | Installs Kirby in `kirby/`. |
| `@playwright/test` | 1.65.0 | Development only | Regression checks for the static site's desktop/mobile interactions. |
| `bnomei/kirby-mcp` | 1.15.1 evaluated | Deferred | Kirby 5 agent tooling, pending a compatible patched SDK. |

No application plugins are installed.
Playwright is test tooling and is never included in the published website.
Inter Tight Regular is self-hosted under its SIL Open Font License, stored with the font in `assets/fonts/OFL.txt`.
Matomo and other analytics are omitted.
The static exporter uses Kirby's own APIs and needs no static-site plugin.
Staticache caches responses for PHP hosting and does not provide the build-time export required here.
Newsletter, Uniform, admin-bar, feedback and rich-text plugins are outside this scaffold's scope.
Kirbytext is sufficient for the initial project stories and about page.

## Interactions

Normal public templates do not create sessions, cookies, CSRF tokens or editor UI.
The local Panel uses Kirby authentication and local account/session files, which are ignored by Git.
The build disables Panel and API and generates thumbnails before uploading the static artifact.
It renders public pages into `dist/`, copies public assets and publishes only media referenced by those renders.
It never copies the local `media/`, `site/`, `content/`, `vendor/` or `kirby/` directories into the artifact.

## MCP decision

Checked on 2026-11-12 against the playbook's Kirby 5 compatible `^1.15` series.
Version 1.15.1 requires `mcp/sdk:^0.8.1`, whose available 0.8.x versions are affected by [GHSA-f4rf-6ghj-mhr6](https://github.com/modelcontextprotocol/php-sdk/security/advisories/GHSA-f4rf-6ghj-mhr6).
The patched SDK series starts at 0.9.0, outside that dependency constraint.
The dependency was removed rather than retaining an audit failure in the scaffold.
No MCP adapter, runtime or copied skills are installed.
Recheck the package's Kirby compatibility and Composer audit before adding it later.

## Adding a plugin

1. Check the upstream README, Kirby 5 support, Composer constraints and security advisories.
2. Install through Composer with an explicit compatible constraint.
3. Keep Composer-installed plugin folders ignored by Git.
4. Check sessions, cookies, CSRF tokens, authenticated output, route names and snippet collisions.
5. Verify local Panel and anonymous frontend behavior over HTTP.
6. Rebuild and check the static artifact, including media and subpaths.
7. Document the plugin's purpose, storage, static compatibility and interactions here.
