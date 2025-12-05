# Media Gallery Downloader

A web application built with Laravel and FrankenPHP for downloading and managing media content from various online sources.

## Features

- **Multi-source Media Downloading**: Download content from various online platforms
- **Direct File Upload**: Upload local media files directly to the gallery
- **Gallery Management**: Organize downloaded media in a gallery interface
- **High Performance**: Built on FrankenPHP for optimal performance
- **Secure**: Rootless containerized deployment with proper security headers
- **Responsive Design**: Works seamlessly on desktop and mobile devices
- **Background Processing**: Queue-based processing for downloads and uploads
- **SQLite Database**: Lightweight, file-based database for easy deployment

## Technology Stack

- **Backend**: Laravel 12, PHP 8.4, FrankenPHP
- **Frontend**: Filament 3, Alpine.js, Tailwind CSS v4
- **Frontend Build**: Deno + Vite + Laravel Vite Plugin
- **Unit Testing**: Pest PHP
- **Acceptance Testing**: Deno with Playwright
- **Database**: SQLite
- **Web Server**: Caddy with HTTP/3 support
- **Container**: Docker with multi-stage builds
- **Process Management**: Supervisor for background services

## Prerequisites

- Docker and Docker Compose
- Git
- Linux/macOS/Windows with WSL2

## Quick Start

### 1. Clone the Repository

```bash
git clone <repository-url>
cd media-gallery-downloader
```

### 2. Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Set your user ID for proper permissions (Linux/macOS)
export UID=$(id -u)
export GID=$(id -g)
```

### 3. Start the Application

```bash
# Build and start the application
docker compose up -d --build
```

### 4. Access the Application

- **HTTP**: <http://localhost:8080>

The application serves HTTP only by default. For HTTPS, place behind a reverse proxy (see below).

The application will automatically:

- Install PHP dependencies
- Set up the database
- Run migrations and seeders
- Build frontend assets
- Start background services

## LAN Deployment

To make the application accessible to other devices on your local network:

### 1. Find Your Server's IP Address

```bash
# Linux
ip addr show | grep "inet " | grep -v 127.0.0.1

# macOS
ifconfig | grep "inet " | grep -v 127.0.0.1
```

Note your LAN IP address (e.g., `192.168.1.100`).

### 2. Update Laravel Environment

Edit your `.env` file to match your IP:

```bash
APP_URL=http://192.168.1.100:8080
```

### 3. Restart the Application

```bash
docker compose down
docker compose up -d
```

### 4. Access from Other Devices

Navigate to `http://192.168.1.100:8080` from any device on your network.

> **Note:** For HTTPS on LAN, use a reverse proxy with a self-signed certificate or set up local DNS with a trusted certificate.

## Production Deployment

The application uses environment variables for configuration. Copy `.env.example` to `.env` and customize as needed.

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DOCKER_TARGET` | `production` | Build target (`production` or `development`) |
| `APP_ENV` | `production` | Laravel environment |
| `APP_DEBUG` | `false` | Enable debug mode |
| `LOG_LEVEL` | `warning` | Log verbosity |
| `HTTP_PORT` | `8080` | HTTP port mapping |
| `MEMORY_LIMIT` | `4G` | Container memory limit |
| `TZ` | `UTC` | Timezone |

### Changing the Default Port

By default, the application listens on port 8080 (HTTP). To use a different port:

1. Set the `HTTP_PORT` environment variable in `.env`:

```bash
HTTP_PORT=3000
```

2. Or edit `docker-compose.yml` and change the port mapping:

```yaml
ports:
    - "3000:8080"
```

3. Update your `.env` file with the new port:

```bash
APP_URL=http://192.168.1.100:3000
```

4. Restart the application and access via `http://192.168.1.100:3000`

### Using a Reverse Proxy (Recommended for Production)

For HTTPS support, place the application behind a reverse proxy like Traefik, Nginx Proxy Manager, or Caddy. Example with Traefik labels:

```yaml
labels:
    - "traefik.enable=true"
    - "traefik.http.routers.mgd.rule=Host(`mgd.example.com`)"
    - "traefik.http.routers.mgd.entrypoints=websecure"
    - "traefik.http.routers.mgd.tls.certresolver=letsencrypt"
    - "traefik.http.services.mgd.loadbalancer.server.port=8080"
```

This approach lets you:

- Use your existing SSL certificate infrastructure
- Avoid port conflicts with other services
- Integrate with your existing reverse proxy setup

## Age-Restricted Videos

To download age-restricted YouTube videos, you need to provide authentication cookies from a logged-in YouTube session.

### Export Cookies from Your Browser

1. Install a browser extension like "Get cookies.txt LOCALLY" (available for Chrome and Firefox)
2. Log into YouTube with your Google account
3. Navigate to youtube.com
4. Use the extension to export cookies to a `cookies.txt` file

### Upload Cookies to the Application

1. Open the application and navigate to **Settings**
2. In the **YouTube Authentication** section, click "Upload Cookies File"
3. Select your exported `cookies.txt` file
4. Click "Upload Cookies"

Age-restricted videos should now download successfully.

**Note**: Cookies may expire over time. If age-restricted downloads start failing, export fresh cookies from your browser and upload them again.

## Bulk Import

The bulk import feature allows you to import large numbers of video files directly from a directory, bypassing the web upload interface. This is ideal for migrating existing video collections or importing files that are too large to upload through a browser.

### How It Works

1. **Place video files** in the `incoming` directory
2. **Schedule or manually trigger** the import scan from Settings → Maintenance
3. **Files are queued** for processing in batches to avoid system overload
4. **Each file is imported** with the filename (without extension) as the display name
5. **File dates are preserved** - the file's last modified date becomes the media record's created/updated date
6. **Successfully imported files** are moved to the media storage and renamed to their database ID
7. **Failed imports** are moved to the `failed` directory with an accompanying `.log` file containing error details

### Directory Structure

```text
storage/app/data/
├── media/       # Stored video files
├── thumbnails/  # Generated thumbnails
├── backups/     # Database backups
└── import/
    ├── incoming/    # Place video files here for import
    └── failed/      # Failed imports are moved here
        ├── video.mp4
        └── video.mp4.log    # Contains error details
```

### Accessing the Import Directory

The `storage/app/data/` directory is bind-mounted, so files persist even after `docker compose down -v`. You can place files directly in:

```text
storage/app/data/import/incoming/
```

Or copy files into the container:

```bash
docker cp /path/to/videos/. mgd_app:/app/storage/app/data/import/incoming/
```

### Supported Video Formats

The bulk import supports the same formats as the web upload:

- MP4, MKV, AVI, MOV, WMV, FLV, WebM, M4V, MPG, MPEG, 3GP, OGV

### Scheduling Import Scans

1. Navigate to **Settings** in the application
2. Expand the **Maintenance** section
3. Find **Bulk import scan** row
4. Select the days and time for automatic scans
5. Click **Save**

You can also click **Run Now** to immediately scan for and queue files.

### Performance Considerations

- **Large files**: The import job has a 2-hour timeout to accommodate very large files (100s of GB)
- **Batch processing**: Files are queued in batches (default: 10) with delays between batches to prevent system overload
- **Queue**: Import jobs run on the `imports` queue, separate from regular uploads and downloads
- **Single attempt**: Each file gets one import attempt; failures are logged and the file is preserved in the `failed` directory

### Handling Failed Imports

If an import fails, the file is moved to `storage/app/import/failed/` along with a `.log` file containing:

- Original filename
- Timestamp of the failure
- Error message explaining why it failed

To retry a failed import:

1. Check the `.log` file to understand the failure
2. Fix any issues (e.g., corrupted file, unsupported format)
3. Move the file back to the `incoming` directory
4. Run another import scan

### Import Environment Variables

You can customize the import paths and batch size via environment variables in `.env`:

```bash
# Custom import paths (optional)
MGD_IMPORT_INCOMING_PATH=/custom/path/to/incoming
MGD_IMPORT_FAILED_PATH=/custom/path/to/failed

# Number of files to queue at once (default: 10)
MGD_IMPORT_BATCH_SIZE=10
```

## Backup and Restore

The application includes built-in backup and restore functionality to help you safeguard your media database and migrate between installations.

### Creating Backups

Backups can be created automatically on a schedule or manually:

1. Navigate to **Settings** in the application
2. Expand the **Maintenance** section
3. Find the **Database backup** row
4. Select days and time for automatic backups, then click **Save**
5. Click **Run Now** to create an immediate backup

Backups are stored in `storage/app/data/backups/` and are automatically cleaned up based on the retention period (default: 30 days).

### Downloading Backups

To download a backup for offsite storage or migration:

1. In **Settings** → **Maintenance**, find the **Database backup** row
2. Click the **Download** button
3. Select the backup you want to download from the list

### Restoring from Backup

To restore your media database from a backup:

1. In **Settings** → **Maintenance**, find the **Database backup** row
2. Click the **Restore** button
3. Select your backup file (`.sql`, `.sqlite`, or `.db`)
4. Review the warning and click to upload

**What happens during restore:**

- **Existing records** with matching name + size are skipped (no duplicates created)
- **Records without source URLs** are skipped (they cannot be re-downloaded)
- **Records with source URLs** but missing local files are queued for automatic re-download
- **Records with existing files** have their thumbnails regenerated
- The restore process is additive - it won't delete any existing data
- **Notifications** show a summary of imported, queued, skipped duplicates, and skipped no-source records

**Supported backup formats:**

- SQLite database files (direct copies from this application)
- SQL dump files with INSERT statements

### Migration to a New Server

To migrate your media library to a new server (e.g., Synology NAS):

1. **On the old server:**
   - Create a fresh backup via Settings → Maintenance → Run Now
   - Download the backup file
   - Copy your `storage/app/data/media/` directory (the actual video files)

2. **On the new server:**
   - Set up the application following the Quick Start guide
   - Copy the media files to `./data/media/` (or `./storage/app/data/media/` for development)
   - Go to Settings → Maintenance → Restore
   - Upload your backup file

3. **After restore:**
   - Videos with local files will be immediately available
   - Videos without local files (but with source URLs) will be queued for download
   - Check the queue status in the application to monitor re-downloads

## Development

### File Structure

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

### Docker Architecture

The Docker stack is designed with security and performance in mind:

#### Security Features

- **Network isolation** - Valkey on internal-only network, not exposed to host
- **Non-root user** - Application runs as `app` user (UID 1000)
- **Minimal attack surface** - Production image excludes dev tools (vim, git, wget)
- **Dropped capabilities** - All Linux capabilities dropped
- **No privilege escalation** - `no-new-privileges` security option enabled
- **Log rotation** - Automatic log file size limits prevent disk exhaustion

#### Volumes and Data Persistence

| Path | Type | Persists `down -v` | Purpose |
|------|------|-------------------|---------|
| `./storage/app/data` | Bind mount | ✅ Yes | Media, thumbnails, backups, imports |
| `./database` | Bind mount | ✅ Yes | SQLite database |
| `mgd_valkey_data` | Named volume | ❌ No | Session, cache, queue data |

#### Networks

| Network | Type | Purpose |
|---------|------|---------|
| `mgd_internal` | Internal bridge | App ↔ Valkey communication (no external access) |
| `mgd_external` | Bridge | App ↔ Internet (HTTP/HTTPS traffic) |

#### Services

- **mgd_app** - FrankenPHP application server with Horizon queue workers
- **mgd_valkey** - Redis-compatible cache/session/queue store (Valkey 9 Alpine, read-only)

### Local Development Setup

For development, set these environment variables in `.env`:

```bash
DOCKER_TARGET=development
APP_ENV=local
APP_DEBUG=true
LOG_LEVEL=debug
```

Then rebuild:

```bash
docker compose up -d --build
```

The development build:

- Installs dev dependencies (Composer, Deno)
- Enables PHP error display
- Disables opcache optimization for faster code changes

```bash
# View logs
docker compose logs -f mgd_app

# Watch for frontend changes (hot reload for CSS/JS)
docker compose exec mgd_app deno task dev

# Or run Vite dev server outside container if you have Deno installed
deno task dev
```

### Frontend Build

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

### Linting & Code Quality

The project uses automated linting for both PHP and JavaScript with pre-commit hooks.

#### Setup (First Time)

After cloning the repository, initialize the git hooks:

```bash
deno task prepare
```

#### PHP Linting

```bash
# Check code style + run static analysis
composer lint

# Auto-fix code style with Laravel Pint
composer lint:fix

# Run PHPStan static analysis only
composer analyse
```

#### JavaScript Linting

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

#### Pre-commit Hook

The pre-commit hook automatically runs on staged files:

- **PHP files**: Auto-fixed with Laravel Pint
- **JS/TS files**: Formatted with Deno fmt, then linted

**Configuration Files:**

| File | Purpose |
|------|---------|
| `phpstan.neon` | PHPStan static analysis config (level 5) |
| `lint-staged.config.js` | Lint-staged file patterns |
| `.husky/pre-commit` | Git pre-commit hook |

#### Production vs Development Comparison

| Feature | Production | Development |
|---------|------------|-------------|
| `DOCKER_TARGET` | `production` | `development` |
| Dependencies | Pre-built in image | Installed at startup |
| Frontend assets | Pre-built in image | Built at startup |
| Dev tools | None (minimal image) | vim, git, wget, deno, composer |
| Opcache | JIT enabled | Validates timestamps |
| Error display | Off | On |
| Startup time | ~5s | ~30s (installs deps) |
| `php artisan optimize` | Runs automatically | Skipped |
| Image size | ~1.1GB | ~1.5GB |

#### Testing Production Build Locally

```bash
# Ensure .env has production settings (or unset dev vars)
unset DOCKER_TARGET APP_DEBUG
docker compose up -d --build
```

### Testing

#### Unit Tests

Unit tests are written using Pest PHP and cover services, models, and Filament pages.

```bash
# Run all unit tests
docker compose exec mgd_app php vendor/bin/pest

# Run only unit tests
docker compose exec mgd_app php vendor/bin/pest tests/Unit

# Run only feature tests
docker compose exec mgd_app php vendor/bin/pest tests/Feature

# Run tests with coverage
docker compose exec mgd_app php vendor/bin/pest --coverage
```

#### Acceptance Tests

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

## AI Assistance Disclaimer

This is a hobby project to solve a personal need and learn how AI tools can be used. AI was used to help with:

- 'Bells and whistles' like the stats page and parts of the settings page
- Styling
- IDE based code completion and suggestions
- Documentation and README content
- Configuration files and Docker setup
- Testing

While AI assistance was utilized, all code has been reviewed, tested, and customized for this specific application. Users should review and understand the code before deployment in production environments.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Legal Notice

This application automatically downloads and utilizes the following third-party software at runtime:

- **yt-dlp**: Licensed under The Unlicense (Public Domain). Automatically downloaded from GitHub releases for media downloading functionality.
- **FFmpeg**: Licensed under LGPL v2.1+ (or GPL v2.1+ depending on build configuration). Installed via system package manager during container build.

**Important**: These tools are not distributed with this application but are automatically obtained during installation/runtime. Users are responsible for:

1. Ensuring compliance with the licensing terms of downloaded components
2. Verifying that their use complies with the terms of service of any websites or platforms from which they download content
3. Compliance with applicable copyright and intellectual property laws in their jurisdiction
4. Understanding that some FFmpeg codecs may have patent restrictions

By using this software, you acknowledge that you are responsible for the legal implications of downloading and using media content.
