# Development

Architecture, local development, and testing. For deploying see
[DEPLOYMENT.md](DEPLOYMENT.md); for configuring a running instance see
[CONFIGURATION.md](CONFIGURATION.md).


## File Structure

```text
media-gallery-downloader/
├── .docker/
│   ├── Caddyfile               # Web server configuration
│   ├── Dockerfile              # Multi-stage container definition
│   ├── php.ini                 # PHP configuration (production)
│   ├── php.dev.ini             # PHP configuration (development)
│   ├── start.sh                # Container startup script
│   ├── supervisord.conf        # Process management
│   └── utilities.sh            # Shell utility functions
├── .dockerignore               # Files excluded from Docker builds
├── .husky/                     # Git hooks (pre-commit)
├── deno.json                   # Deno config, tasks, and npm imports
├── vite.config.js              # Vite bundler configuration
├── tailwind.config.js          # Tailwind CSS content paths
├── postcss.config.js           # PostCSS plugins
├── phpstan.neon                # PHPStan static analysis config
├── lint-staged.config.js       # Lint-staged file patterns
├── app/                        # Laravel application code
├── config/                     # Laravel configuration files
├── database/                   # Migrations, factories, and seeds
├── lang/                       # Language files
├── public/                     # Web-accessible files
├── resources/                  # Views and frontend assets
├── routes/                     # Route definitions
├── storage/                    # File storage and logs
│   └── app/
│       └── data/               # Persistent data (bind mounted)
│           ├── media/          # Video files
│           ├── thumbnails/     # Generated thumbnails
│           ├── backups/        # Database backups
│           └── import/         # Bulk import directories
├── tests/                      # Pest and Playwright tests
└── docker-compose.yml          # Container orchestration
```

## Docker Architecture

The Docker stack is designed with security and performance in mind:

### Security Features

- **Network isolation** - Valkey on internal-only network, not exposed to host
- **Non-root user** - Application runs as `app` user (UID 1000)
- **Minimal attack surface** - Production image excludes dev tools (vim, git, wget)
- **Dropped capabilities** - All Linux capabilities dropped
- **No privilege escalation** - `no-new-privileges` security option enabled
- **Log rotation** - Automatic log file size limits prevent disk exhaustion

### Volumes and Data Persistence

| Path (Production) | Path (Development) | Type | Persists `down -v` | Purpose |
|-------------------|-------------------|------|-------------------|--------|
| `./storage` | `./storage` | Bind mount | ✅ Yes | All app data: database, media, logs, cache |
| `<project>_valkey_data` | `<project>_valkey_data` | Named volume | ❌ No | Session, cache, queue data |

### Networks

| Network | Type | Purpose |
|---------|------|---------|
| `<project>_internal` | Internal bridge | App ↔ Valkey communication (no external access) |
| `<project>_external` | Bridge | App ↔ Internet (HTTP/HTTPS traffic) |

### Services

- **app** - FrankenPHP application server with Horizon queue workers
- **valkey** - Redis-compatible cache/session/queue store (Valkey 9 Alpine, read-only)

### Running Multiple Instances

You can run multiple independent stacks on the same system. Each stack gets isolated resources (networks, volumes) based on its directory name:

```bash
# Instance 1 - Personal media
mkdir mgd-personal && cd mgd-personal
curl -fsSL https://raw.githubusercontent.com/media-gallery-downloader/media-gallery-downloader/master/mgd.sh -o mgd.sh
chmod +x mgd.sh && ./mgd.sh install
# Edit .env: HTTP_PORT=8080

# Instance 2 - Family media
mkdir ../mgd-family && cd ../mgd-family
curl -fsSL https://raw.githubusercontent.com/media-gallery-downloader/media-gallery-downloader/master/mgd.sh -o mgd.sh
chmod +x mgd.sh && ./mgd.sh install
# Edit .env: HTTP_PORT=8081
```

Each directory becomes its own isolated stack with prefixed resources like `mgd-personal_valkey_data`, `mgd-family_valkey_data`, etc.

## Local Development Setup

For development, first clone the repository:

```bash
git clone https://github.com/media-gallery-downloader/media-gallery-downloader.git
cd media-gallery-downloader
cp .env.development .env
```

Then build and start using the development compose file:

```bash
docker compose -f docker-compose.dev.yml up -d --build
```

The development build:

- Installs dev dependencies (Composer, Deno)
- Enables PHP error display
- Disables opcache optimization for faster code changes

```bash
# View logs
docker compose -f docker-compose.dev.yml logs -f app

# Watch for frontend changes (hot reload for CSS/JS)
docker compose -f docker-compose.dev.yml exec app deno task dev

# Or run Vite dev server outside container if you have Deno installed
deno task dev
```

## Frontend Build

The frontend uses **Deno + Vite** with the Laravel Vite plugin for asset bundling:

```bash
# Development (hot reload)
deno task dev

# Production build
deno task build

# Preview production build
deno task preview
```

**Build Configuration:**

| File | Purpose |
|------|---------|  
| `deno.json` | Deno tasks and npm package imports |
| `vite.config.js` | Vite config with Laravel and Tailwind v4 plugins |
| `tailwind.config.js` | Content paths for Blade, JS, Vue, Filament |
| `postcss.config.js` | PostCSS with autoprefixer |

> **Note**: In Docker, the frontend is built during the image build (production) or at container startup (development). You don't need Deno installed locally unless doing frontend development outside Docker.

## Linting & Code Quality

The project uses automated linting for both PHP and JavaScript with pre-commit hooks.

### Setup (First Time)

After cloning the repository, initialize the git hooks:

```bash
deno task prepare
```

### PHP Linting

```bash
# Check code style + run static analysis
composer lint

# Auto-fix code style with Laravel Pint
composer lint:fix

# Run PHPStan static analysis only
composer analyse
```

### JavaScript Linting

```bash
# Lint JS files
deno task lint

# Lint + auto-fix
deno task lint:fix

# Format JS files
deno task fmt

# Check formatting without changes
deno task fmt:check
```

### Pre-commit Hook

The pre-commit hook automatically runs on staged files:

- **PHP files**: Auto-fixed with Laravel Pint
- **JS/TS files**: Formatted with Deno fmt, then linted

**Configuration Files:**

| File | Purpose |
|------|---------|
| `phpstan.neon` | PHPStan static analysis config (level 5) |
| `lint-staged.config.js` | Lint-staged file patterns |
| `.husky/pre-commit` | Git pre-commit hook |

### Production vs Development Comparison

| Feature | Production | Development |
|---------|------------|-------------|
| Compose file | `docker-compose.yml` | `docker-compose.dev.yml` |
| Dependencies | Pre-built in image | Installed at startup |
| Frontend assets | Pre-built in image | Built at startup |
| Dev tools | None (minimal image) | vim, git, wget, deno, composer |
| Opcache | JIT enabled | Validates timestamps |
| Error display | Off | On |
| Startup time | ~5s | ~30s (installs deps) |
| `php artisan optimize` | Runs automatically | Skipped |
| Image size | ~1.1GB | ~1.5GB |

### Testing Production Build Locally

```bash
# Ensure .env has production settings
unset APP_DEBUG
docker compose up -d --build
```

## Testing

### Unit Tests

Unit tests are written using Pest PHP and cover services, models, and Filament pages.

```bash
# Run all unit tests
docker compose exec app php vendor/bin/pest

# Run only unit tests
docker compose exec app php vendor/bin/pest tests/Unit

# Run only feature tests
docker compose exec app php vendor/bin/pest tests/Feature

# Run tests with coverage
docker compose exec app php vendor/bin/pest --coverage
```

### Acceptance Tests

Acceptance tests use Deno with Playwright for browser-based end-to-end testing. Tests run in both Chromium and Firefox.

**Prerequisites:**

- Deno 2.x installed locally
- Application running via Docker

```bash
# Install browser binaries (first time only)
cd tests/e2e
deno task install:browsers

# Run acceptance tests
deno task test

# Run specific test file
deno test -A app_test.ts
```

**Test Structure:**

- `tests/Unit/` - Pest unit tests for services and models
- `tests/Feature/` - Pest feature tests for Filament pages
- `tests/e2e/` - Deno acceptance tests for browser interactions

