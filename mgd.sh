#!/bin/bash
# =============================================================================
# Media Gallery Downloader - Management Script
# =============================================================================
# Usage:
#   ./mgd.sh           # Install or update
#   ./mgd.sh install   # Fresh install
#   ./mgd.sh update    # Update existing installation
#   ./mgd.sh start     # Start containers
#   ./mgd.sh stop      # Stop containers
#   ./mgd.sh logs      # View logs
#
# One-liner install:
#   curl -fsSL https://raw.githubusercontent.com/media-gallery-downloader/media-gallery-downloader/master/mgd.sh | bash
# =============================================================================

set -e

REPO_URL="https://raw.githubusercontent.com/media-gallery-downloader/media-gallery-downloader/master"
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

log() { echo -e "${GREEN}[MGD]${NC} $1"; }
warn() { echo -e "${YELLOW}[MGD]${NC} $1"; }
error() { echo -e "${RED}[MGD]${NC} $1"; exit 1; }

# Check for required commands
check_requirements() {
    command -v docker >/dev/null 2>&1 || error "Docker is required but not installed."
    command -v curl >/dev/null 2>&1 || error "curl is required but not installed."
    
    # Check if docker compose is available (v2 or plugin)
    if docker compose version >/dev/null 2>&1; then
        COMPOSE_CMD="docker compose"
    elif command -v docker-compose >/dev/null 2>&1; then
        COMPOSE_CMD="docker-compose"
    else
        error "Docker Compose is required but not installed."
    fi
}

# Download file from repo
download_file() {
    local file=$1
    local dest=${2:-$file}
    log "Downloading $file..."
    curl -fsSL "$REPO_URL/$file" -o "$dest"
}

# Install or update
install() {
    log "Media Gallery Downloader - Install/Update"
    echo ""
    
    check_requirements
    
    # Update mgd.sh itself
    log "Updating mgd.sh..."
    curl -fsSL "$REPO_URL/mgd.sh" -o "mgd.sh.new"
    if [ -f "mgd.sh.new" ]; then
        mv mgd.sh.new mgd.sh
        chmod +x mgd.sh
    fi
    
    # Download docker-compose.yml (always update)
    download_file "docker-compose.yml"
    
    # Create .env if it doesn't exist
    if [ ! -f ".env" ]; then
        log "Creating .env from template..."
        download_file ".env.example" ".env"
        warn "Edit .env to customize settings (timezone, port, etc.)"
    else
        log ".env already exists, preserving your configuration"
    fi
    
    # Get DATA_PATH from .env or use default
    DATA_PATH=$(grep -E "^DATA_PATH=" .env 2>/dev/null | cut -d'=' -f2 || echo "./storage")
    DATA_PATH=${DATA_PATH:-./storage}
    
    # Create storage directory structure (required because mount overlays container's /app/storage)
    log "Creating storage directory structure at $DATA_PATH..."
    mkdir -p "$DATA_PATH/app/db"
    mkdir -p "$DATA_PATH/app/data/import/incoming"
    mkdir -p "$DATA_PATH/app/data/import/failed"
    mkdir -p "$DATA_PATH/app/data/media"
    mkdir -p "$DATA_PATH/app/data/thumbnails"
    mkdir -p "$DATA_PATH/app/data/backups"
    mkdir -p "$DATA_PATH/framework/cache"
    mkdir -p "$DATA_PATH/framework/sessions"
    mkdir -p "$DATA_PATH/framework/views"
    mkdir -p "$DATA_PATH/logs"
    
    # Fix permissions (container runs as UID 1000)
    fix_permissions() {
        log "Setting permissions on $DATA_PATH..."
        if [ "$(id -u)" = "0" ]; then
            chown -R 1000:1000 "$DATA_PATH"
        elif command -v sudo >/dev/null 2>&1; then
            sudo chown -R 1000:1000 "$DATA_PATH"
        else
            warn "Could not set permissions. Run: sudo chown -R 1000:1000 $DATA_PATH"
            return 1
        fi
    }
    
    # Check if permissions need fixing (test if UID 1000 can write)
    # Create a test file to check actual write permissions
    TEST_FILE="$DATA_PATH/.permission_test"
    if ! touch "$TEST_FILE" 2>/dev/null; then
        fix_permissions
    else
        rm -f "$TEST_FILE"
        # Also check if files are owned by 1000 (for existing installs)
        OWNER=$(stat -c '%u' "$DATA_PATH" 2>/dev/null || stat -f '%u' "$DATA_PATH" 2>/dev/null)
        if [ "$OWNER" != "1000" ]; then
            fix_permissions
        fi
    fi
    
    # Pull latest image
    log "Pulling latest image..."
    $COMPOSE_CMD pull
    
    # Start or restart
    if $COMPOSE_CMD ps -q mgd_app >/dev/null 2>&1; then
        log "Restarting containers..."
        $COMPOSE_CMD down
        $COMPOSE_CMD up -d
    else
        log "Starting containers..."
        $COMPOSE_CMD up -d
    fi
    
    echo ""
    log "Installation complete!"
    echo ""
    
    # Get the port from .env or default
    HTTP_PORT=$(grep -E "^HTTP_PORT=" .env 2>/dev/null | cut -d'=' -f2 || echo "8080")
    HTTP_PORT=${HTTP_PORT:-8080}
    
    log "Access the application at: http://localhost:${HTTP_PORT}"
    echo ""
    log "Useful commands:"
    echo "  ./mgd.sh logs      # View logs"
    echo "  ./mgd.sh stop      # Stop"
    echo "  ./mgd.sh start     # Start"
    echo "  ./mgd.sh update    # Update to latest version"
}

# Update only (alias for install)
update() {
    log "Updating Media Gallery Downloader..."
    install
}

# Start containers
start() {
    check_requirements
    log "Starting containers..."
    $COMPOSE_CMD up -d
    log "Started!"
}

# Stop containers
stop() {
    check_requirements
    log "Stopping containers..."
    $COMPOSE_CMD down
    log "Stopped!"
}

# View logs
logs() {
    check_requirements
    $COMPOSE_CMD logs -f
}

# Main
case "${1:-install}" in
    install|update)
        install
        ;;
    start)
        start
        ;;
    stop)
        stop
        ;;
    logs)
        logs
        ;;
    *)
        echo "Usage: $0 [install|update|start|stop|logs]"
        exit 1
        ;;
esac
