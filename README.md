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

- **Backend**: Laravel 11, PHP 8.4, FrankenPHP
- **Frontend**: Filament 3, Alpine.js, Tailwind CSS
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
# Build and start the container
docker compose up --build

# Or run in background
docker compose up -d --build
```

### 4. Access the Application

- **HTTPS**: <https://mgd.localhost> (with self-signed certificate)

**Certificate Warning**: When first accessing the application, your browser will display a security warning about the self-signed certificate. This is normal for local development and/or self hosting. To proceed:

- **Chrome/Edge**: Click "Advanced" -> "Proceed to mgd.localhost (unsafe)"
- **Firefox**: Click "Advanced" -> "Accept the Risk and Continue"
- **Safari**: Click "Show Details" -> "visit this website" -> "Visit Website"

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

### 2. Update the Caddyfile

Edit `.docker/Caddyfile` and replace `mgd.localhost` with your server's IP address:

```caddyfile
192.168.1.100 {
    root * /app/public

    @static {
        file
        path *.css *.js *.png *.jpg *.jpeg *.gif *.webp *.svg *.ico *.woff *.woff2 *.ttf *.eot
    }
    header @static Cache-Control "public, max-age=31536000, immutable"

    encode zstd br gzip

    header {
        X-Content-Type-Options nosniff
        X-Frame-Options DENY
        X-XSS-Protection "1; mode=block"
        Referrer-Policy strict-origin-when-cross-origin
    }

    try_files {path} {path}/ /index.php?{query}
    php_server

    file_server
}
```

### 3. Update Laravel Environment

Edit your `.env` file to match:

```bash
APP_URL=https://192.168.1.100
```

### 4. Restart the Application

```bash
docker compose down
docker compose up -d --build
```

### 5. Access from Other Devices

Navigate to `https://192.168.1.100` from any device on your network.

Note: You will see a certificate warning on each device since Caddy generates a self-signed certificate. Accept the certificate to proceed.

### Changing the Default Port

By default, the application listens on ports 80 (HTTP) and 443 (HTTPS). To use different ports:

1. Edit `docker-compose.yml` and change the port mappings:

```yaml
ports:
    - "8100:8080"      # HTTP: access via port 8100
    - "8143:8443"      # HTTPS: access via port 8143
    - "8143:8443/udp"  # HTTP/3
```

2. Update your `.env` file with the new port:

```bash
APP_URL=https://192.168.1.100:8143
```

4. Restart the application and access via `https://192.168.1.100:8143`

## Development

### File Structure

```text
media-gallery-downloader/
├── .docker/
│   ├── Caddyfile               # Web server configuration
│   ├── Dockerfile              # Container definition
│   ├── php.ini                 # PHP configuration overrides
│   ├── start.sh                # Container startup script
│   ├── supervisord.conf        # Process management
│   └── utilities.sh            # Shell utility functions
├── app/                        # Laravel application code
├── config/                     # Laravel configuration files
├── database/                   # Migrations, factories, and seeds
├── lang/                       # Language files
├── public/                     # Web-accessible files
├── resources/                  # Views and frontend assets
├── routes/                     # Route definitions
├── storage/                    # File storage and logs
├── tests/                      # Pest and Playwright tests
├── docker-compose.yml          # Container orchestration
└── phpunit.xml                 # Test configuration
```

### Local Development Setup

For development with live reloading:

```bash
# Start the stack
docker compose up -d

# Watch for frontend changes (in container)
docker compose exec mgd_app deno task dev

# Or run outside container if you have Deno installed
deno task dev
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
