#!/usr/bin/env bash
set -e

# Color output for better readability
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

warn() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}"
}

# Cleanup function
cleanup() {
    log "Gracefully shutting down..."
    
    # Stop Octane gracefully
    if pgrep -f "octane:frankenphp" > /dev/null; then
        pkill -TERM -f "octane:frankenphp" 2>/dev/null || true
    fi
    
    # Stop supervisor
    if [ ! -z "$SUPERVISOR_PID" ] && kill -0 $SUPERVISOR_PID 2>/dev/null; then
        supervisorctl -c /etc/supervisor/supervisord.conf stop all 2>/dev/null || true
        kill $SUPERVISOR_PID 2>/dev/null || true
    fi
    
    exit 0
}

# Set up signal handlers
trap cleanup SIGTERM SIGINT

# Detect environment
IS_DEV=false
if [ "$APP_ENV" = "local" ] || [ "$APP_ENV" = "development" ]; then
    IS_DEV=true
    log "Running in DEVELOPMENT mode"
else
    log "Running in PRODUCTION mode"
fi

# =============================================================================
# Environment Setup (idempotent - only runs if needed)
# =============================================================================

# Copy environment file if it doesn't exist
if [ ! -f .env ]; then
    log "Creating .env file from example..."
    cp .env.example .env
    
    # Generate application key only for new installations
    log "Generating application key..."
    php artisan key:generate --force
fi

# Create SQLite database if it doesn't exist
if [ ! -f database/database.sqlite ]; then
    log "Creating SQLite database..."
    touch database/database.sqlite
    chmod 666 database/database.sqlite
fi

# Create test database if it doesn't exist (for development/testing)
if [ ! -f database/testing.sqlite ]; then
    log "Creating test SQLite database..."
    touch database/testing.sqlite
    chmod 666 database/testing.sqlite
fi

# =============================================================================
# Dependencies (development only - production has pre-built deps)
# =============================================================================

if [ "$IS_DEV" = true ]; then
    # Install PHP dependencies with dev requirements
    log "Installing PHP dependencies (with dev)..."
    composer install --no-interaction --prefer-dist
    
    # Install frontend dependencies
    log "Installing frontend dependencies..."
    deno install
    
    # Build frontend assets if needed
    if [ ! -f public/build/manifest.json ] || [ resources/js/app.js -nt public/build/manifest.json ] || [ resources/css/app.css -nt public/build/manifest.json ]; then
        log "Building frontend assets..."
        deno task build
    else
        log "Frontend assets up to date"
    fi
fi

# =============================================================================
# Laravel Setup (all commands are idempotent)
# =============================================================================

# Create storage link (--force overwrites existing, --relative for portability)
log "Ensuring storage link exists..."
php artisan storage:link --relative --force 2>/dev/null || true

# Install Octane if not already installed
if [ ! -f config/octane.php ]; then
    log "Installing Laravel Octane..."
    php artisan octane:install --server=frankenphp --no-interaction
fi

# Run migrations (only runs pending migrations)
log "Running database migrations..."
php artisan migrate --force --no-interaction

# Seed only if the settings table is empty (first run)
if ! php artisan tinker --execute="echo App\Models\Setting::count();" 2>/dev/null | grep -q "^[1-9]"; then
    log "Seeding database..."
    php artisan db:seed --force --no-interaction 2>/dev/null || true
fi

# =============================================================================
# Optimization (production only)
# =============================================================================

if [ "$IS_DEV" = false ]; then
    log "Optimizing application for production..."
    php artisan optimize --no-interaction
fi

# =============================================================================
# Start Services
# =============================================================================

log "Starting supervisor for background services..."

# Start supervisor for queue and scheduler
supervisord -c /etc/supervisor/supervisord.conf &
SUPERVISOR_PID=$!

log "Starting FrankenPHP..."

# Set Caddy data directory
export XDG_DATA_HOME=/app/storage
export XDG_CONFIG_HOME=/app/storage

# Start FrankenPHP
exec /usr/local/bin/frankenphp run --config /app/.docker/Caddyfile
