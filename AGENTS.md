# AGENTS.md

Instructions for any AI agent (Claude Code, GitHub Copilot, Codex, etc.) working
on this repository. This is the single source of truth; tool-specific files
(e.g. `.github/copilot-instructions.md`) point here.

## Verifying changes

- Use the Docker dev stack (`docker compose -f docker-compose.dev.yml up -d --build`)
  for manual or browser-based verification — it mirrors production (FrankenPHP,
  Valkey, baked config). Don't stand up ad-hoc servers.
- **Never preview media playback through `php artisan serve`.** PHP's built-in
  server breaks the Vidstack player's time/duration state (frozen at 0:00 while
  the video plays), producing false player "bugs" that don't reproduce under a
  real server.
- Frontend tooling is Deno (`deno task build` / `deno task dev`), not npm/bun.
  PHP quality gates: Laravel Pint + PHPStan (level 5). Tests: Pest, plus
  Playwright E2E under `tests/e2e` (headless; against the dev stack set
  `APP_URL=http://localhost:<HTTP_PORT>`).
- The dev stack bind-mounts the repo, `storage/` included, so tests run in it
  share the dev app's live data (cookies, temp files, logs). Keep tests on
  throwaway paths; see "Unit Tests" in `docs/DEVELOPMENT.md`. Never delete
  `storage/app/temp`: it is PHP's temp and upload directory.
- Don't run one-off npm tools with `deno run npm:…` from the repo root: Deno
  installs them into the repo's `node_modules` and rewrites `deno.lock`. Run
  them from another directory.

## Gotchas

- Bumping `YTDLP_VERSION` means reviewing yt-dlp's new option destinations and
  regenerating `resources/python/ytdlp_known_destinations.json`; see "When
  yt-dlp Changes" in `docs/DEVELOPMENT.md`.
- `resources/views/vendor/` holds published package views that override the
  installed ones and go stale. If a Filament component misbehaves after an
  update, compare them with `vendor/filament/*/resources/views/` first.
- Cookie files under `storage/app/cookies/` are secrets: never render, log or
  copy their contents (only counts and domains).

## CI & releases

- Every push to `master` builds multi-arch images (amd64 + arm64, ~35 minutes)
  for the next version (a Conventional-Commit-driven bump), then tags and
  publishes the release. The tag and release wait for the images, so a failed
  build creates neither; once a rerun of the failed jobs passes, the release
  follows. Docs-only commits should include `[skip ci]` in the message to avoid
  an extra build and release.
- Pull requests build the image without pushing it, and only when one of its
  inputs changes: `.docker/`, `.dockerignore`, the Composer and Deno
  manifests, the other files the Dockerfile copies by name, or `ci.yml`. When
  the Dockerfile starts copying another file by name, add it to the `changes`
  job's list.
- The arm64 image leg builds under qemu emulation. If a stage dies with exit
  132 (`qemu: uncaught target signal 4`), that is a known GitHub-runner flake —
  rerun the job before investigating. The frontend stage is pinned to
  `--platform=$BUILDPLATFORM` so deno (V8) never runs emulated; keep it that
  way.
- Dependabot PRs opened before a dependency security fix landed on `master`
  fail `composer audit` until rebased (comment `@dependabot rebase`).
