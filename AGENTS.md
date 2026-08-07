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
  Playwright E2E under `tests/e2e`.

## CI & releases

- Every push to `master` mints a release (Conventional-Commit-driven version
  bump) and builds multi-arch images (amd64 + arm64), taking ~35 minutes.
  Docs-only commits should include `[skip ci]` in the message to avoid an extra
  build and release.
- The arm64 image leg builds under qemu emulation. If a stage dies with exit
  132 (`qemu: uncaught target signal 4`), that is a known GitHub-runner flake —
  rerun the job before investigating. The frontend stage is pinned to
  `--platform=$BUILDPLATFORM` so deno (V8) never runs emulated; keep it that
  way.
- Dependabot PRs opened before a dependency security fix landed on `master`
  fail `composer audit` until rebased (comment `@dependabot rebase`).
