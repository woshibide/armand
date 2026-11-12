# Armand Demulder

Personal portfolio built with Kirby 5 and exported to GitHub Pages.
Website source lives in [`www/`](www/README.md).

```sh
cd www
composer install
composer start
```

Read [the design study](STUDIO-DUMBAR-DESIGN-STUDY.md) before changing the layout or scrolling behavior.
Reference screenshots live in [`design-reference/`](design-reference/), outside the public website.
GitHub Actions builds and tests `www/`, then publishes only `www/dist/`.
