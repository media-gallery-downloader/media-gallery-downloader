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
        sudo supervisorctl -c /etc/supervisor/supervisord.conf stop all 2>/dev/null || true
        kill $SUPERVISOR_PID 2>/dev/null || true
    fi
    
    exit 0
}

# Set up signal handlers
trap cleanup SIGTERM SIGINT

# First-time setup
if [ ! -f init.lock ]; then
    log "Running first-time setup..."
    
    # Copy environment file
    if [ ! -f .env ]; then
        log "Creating .env file from example"
        cp .env.example .env
    fi

    # Create SQLite database
    if [ ! -f database/database.sqlite ]; then
        log "Creating SQLite database"
        touch database/database.sqlite
        chmod 666 database/database.sqlite
    fi

    # Install dependencies
    log "Installing PHP dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader

    # Create storage link
    log "Creating storage link..."
    php artisan storage:link --relative --force

    # Generate application key
    log "Generating application key..."
    php artisan key:generate --force

    # Install Octane
    log "Installing Laravel Octane..."
    php artisan octane:install --server=frankenphp --no-interaction

    # Run migrations
    log "Running database migrations..."
    php artisan migrate --force --no-interaction --seed

    # Install and build frontend assets
    log "Installing frontend dependencies..."
    bun install --frozen-lockfile
    
    log "Building frontend assets..."
    bun run build

    # Mark initialization as complete
    touch init.lock
    log "First-time setup completed"
fi

# Always run these optimizations
log "Running application optimizations..."

# Dump autoloader
composer dumpautoload --no-interaction --optimize

# Clear caches
php artisan config:clear
php artisan event:clear
php artisan route:clear
php artisan view:clear

if [ "$APP_ENV" = "production" ]; then
    # Optimize application (only in production)
    php artisan optimize --no-interaction
fi

log "Starting supervisor for background services..."

# Start supervisor for queue and scheduler only
sudo supervisord -c /etc/supervisor/supervisord.conf &
SUPERVISOR_PID=$!

log "Starting FrankenPHP with standard mode..."

# Set Caddy data directory to use our custom storage location
export XDG_DATA_HOME=/app/storage
export XDG_CONFIG_HOME=/app/storage

# Start FrankenPHP
exec /usr/local/bin/frankenphp run --config /app/.docker/Caddyfile
