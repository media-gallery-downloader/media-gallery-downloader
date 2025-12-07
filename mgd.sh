#!/bin/bash
# =============================================================================
# Media Gallery Downloader - Management Script
# =============================================================================
# Usage:
#   ./mgd.sh              # Show available commands
#   ./mgd.sh install      # Fresh install (downloads docker-compose.yml and .env)
#   ./mgd.sh update       # Pull latest image and restart
#   ./mgd.sh up           # Create and start containers
#   ./mgd.sh down         # Stop and remove containers
#   ./mgd.sh start        # Start existing containers
#   ./mgd.sh stop         # Stop containers (without removing)
#   ./mgd.sh logs         # View logs
#   ./mgd.sh fixperms     # Fix storage directory permissions
#   ./mgd.sh selfupdate   # Update this script to latest version
#
# One-liner install:
#   curl -fsSL https://raw.githubusercontent.com/media-gallery-downloader/media-gallery-downloader/master/mgd.sh -o mgd.sh && chmod +x mgd.sh && ./mgd.sh install
# =============================================================================

set -e

REPO_URL="https://raw.githubusercontent.com/media-gallery-downloader/media-gallery-downloader/master"
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log() { echo -e "${GREEN}[MGD]${NC} $1"; }
warn() { echo -e "${YELLOW}[MGD]${NC} $1"; }
error() { echo -e "${RED}[MGD]${NC} $1"; exit 1; }
info() { echo -e "${BLUE}[MGD]${NC} $1"; }

# Show usage/help
show_help() {
    echo ""
    echo -e "${GREEN}Media Gallery Downloader - Management Script${NC}"
    echo ""
    echo "Usage: ./mgd.sh <command>"
    echo ""
    echo "Commands:"
    echo "  install      Fresh install (downloads docker-compose.yml and .env)"
    echo "  update       Pull latest image and restart containers"
    echo "  up           Create and start containers"
    echo "  down         Stop and remove containers"
    echo "  start        Start existing containers"
    echo "  stop         Stop containers (without removing)"
    echo "  logs         View container logs (follow mode)"
    echo "  fixperms     Fix storage directory permissions"
    echo "  selfupdate   Update this script to the latest version"
    echo ""
    echo "One-liner install:"
    echo "  curl -fsSL $REPO_URL/mgd.sh -o mgd.sh && chmod +x mgd.sh && ./mgd.sh install"
    echo ""
}

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

# Check if docker-compose.yml exists
require_compose_file() {
    if [ ! -f "docker-compose.yml" ]; then
        error "docker-compose.yml not found. Run './mgd.sh install' first."
    fi
}

# Get DATA_PATH from .env or use default
get_data_path() {
    if [ -f ".env" ]; then
        DATA_PATH=$(grep -E "^DATA_PATH=" .env 2>/dev/null | cut -d'=' -f2 || echo "./storage")
    fi
    DATA_PATH=${DATA_PATH:-./storage}
    echo "$DATA_PATH"
}

# Download file from repo
download_file() {
    local file=$1
    local dest=${2:-$file}
    log "Downloading $file..."
    curl -fsSL "$REPO_URL/$file" -o "$dest"
}

# Create storage directories
create_directories() {
    local data_path=$1
    log "Creating storage directory structure at $data_path..."
    mkdir -p "$data_path/app/db"
    mkdir -p "$data_path/app/data/import/incoming"
    mkdir -p "$data_path/app/data/import/failed"
    mkdir -p "$data_path/app/data/media"
    mkdir -p "$data_path/app/data/thumbnails"
    mkdir -p "$data_path/app/data/backups"
    mkdir -p "$data_path/framework/cache"
    mkdir -p "$data_path/framework/sessions"
    mkdir -p "$data_path/framework/views"
    mkdir -p "$data_path/logs"
}

# Fix permissions (container runs as UID 1000)
do_fix_permissions() {
    local data_path=$1
    log "Setting permissions on $data_path..."
    # Get UID/GID from .env or default to 1000
    local uid=$(grep -E "^UID=" .env 2>/dev/null | cut -d'=' -f2 || echo "1000")
    local gid=$(grep -E "^GID=" .env 2>/dev/null | cut -d'=' -f2 || echo "1000")
    uid=${uid:-1000}
    gid=${gid:-1000}
    
    log "Setting ownership to ${uid}:${gid}..."
    if [ "$(id -u)" = "0" ]; then
        chown -R "${uid}:${gid}" "$data_path"
        chmod -R 755 "$data_path"
    elif command -v sudo >/dev/null 2>&1; then
        sudo chown -R "${uid}:${gid}" "$data_path"
        sudo chmod -R 755 "$data_path"
    else
        warn "Could not set permissions. Run:"
        warn "  sudo chown -R ${uid}:${gid} $data_path"
        warn "  sudo chmod -R 755 $data_path"
        return 1
    fi
    log "Permissions fixed!"
}

# Check and fix permissions if needed
check_and_fix_permissions() {
    local data_path=$1
    # Create a test file to check actual write permissions
    TEST_FILE="$data_path/.permission_test"
    if ! touch "$TEST_FILE" 2>/dev/null; then
        do_fix_permissions "$data_path"
    else
        rm -f "$TEST_FILE"
        # Also check if files are owned correctly (for existing installs)
        local expected_uid=$(grep -E "^UID=" .env 2>/dev/null | cut -d'=' -f2 || echo "1000")
        expected_uid=${expected_uid:-1000}
        OWNER=$(stat -c '%u' "$data_path" 2>/dev/null || stat -f '%u' "$data_path" 2>/dev/null)
        if [ "$OWNER" != "$expected_uid" ]; then
            do_fix_permissions "$data_path"
        fi
    fi
}

# Check if any containers are running
is_running() {
    require_compose_file
    # Get container IDs from compose, don't hardcode names
    if $COMPOSE_CMD ps -q 2>/dev/null | grep -q .; then
        return 0
    fi
    return 1
}

# Install (fresh install with confirmation)
install() {
    log "Media Gallery Downloader - Fresh Install"
    echo ""
    
    check_requirements
    
    # Warn about overwriting files
    if [ -f "docker-compose.yml" ] || [ -f ".env" ]; then
        warn "This will overwrite the following files if they exist:"
        [ -f "docker-compose.yml" ] && echo "  - docker-compose.yml"
        [ -f ".env" ] && echo "  - .env"
        echo ""
        read -p "Continue? [y/N] " -n 1 -r
        echo ""
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            log "Installation cancelled."
            exit 0
        fi
    fi
    
    # Download docker-compose.yml
    download_file "docker-compose.yml"
    
    # Download .env
    log "Creating .env from template..."
    download_file ".env.docker.example" ".env"
    warn "Edit .env to customize settings (timezone, port, etc.)"
    
    # Get DATA_PATH and create directories
    DATA_PATH=$(get_data_path)
    create_directories "$DATA_PATH"
    
    # Fix permissions
    check_and_fix_permissions "$DATA_PATH"
    
    # Pull latest image
    log "Pulling latest image..."
    $COMPOSE_CMD pull
    
    # Start containers
    log "Starting containers..."
    $COMPOSE_CMD up -d
    
    echo ""
    log "Installation complete!"
    echo ""
    
    # Get the port from .env or default
    HTTP_PORT=$(grep -E "^HTTP_PORT=" .env 2>/dev/null | cut -d'=' -f2 || echo "8080")
    HTTP_PORT=${HTTP_PORT:-8080}
    
    log "Access the application at: http://localhost:${HTTP_PORT}"
    echo ""
    log "Useful commands:"
    echo "  ./mgd.sh start       # Start containers"
    echo "  ./mgd.sh stop        # Stop containers"
    echo "  ./mgd.sh logs        # View logs"
    echo "  ./mgd.sh update      # Pull latest image and restart"
    echo "  ./mgd.sh fixperms    # Fix storage permissions"
    echo "  ./mgd.sh selfupdate  # Update this script"
}

# Update (pull latest image, optionally update config files)
update() {
    log "Updating Media Gallery Downloader..."
    echo ""
    
    check_requirements
    require_compose_file
    
    # Timestamp for backup files
    BACKUP_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    
    # Prompt for config file updates
    echo ""
    info "Would you like to update configuration files?"
    info "(Your current files will be backed up with a .$BACKUP_TIMESTAMP.backup suffix)"
    echo ""
    
    # Ask about docker-compose.yml
    read -p "Update docker-compose.yml? [y/N] " -n 1 -r
    echo
    UPDATE_COMPOSE=false
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        UPDATE_COMPOSE=true
    fi
    
    # Ask about .env
    UPDATE_ENV=false
    if [ -f ".env" ]; then
        read -p "Update .env (from .env.docker.example template)? [y/N] " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            UPDATE_ENV=true
        fi
    fi
    
    # Backup and update docker-compose.yml if requested
    if [ "$UPDATE_COMPOSE" = true ]; then
        if [ -f "docker-compose.yml" ]; then
            log "Backing up docker-compose.yml to docker-compose.yml.${BACKUP_TIMESTAMP}.backup"
            cp docker-compose.yml "docker-compose.yml.${BACKUP_TIMESTAMP}.backup"
        fi
        log "Downloading latest docker-compose.yml..."
        curl -fsSL "$REPO_URL/docker-compose.yml" -o docker-compose.yml
    fi
    
    # Backup and update .env if requested
    if [ "$UPDATE_ENV" = true ]; then
        log "Backing up .env to .env.${BACKUP_TIMESTAMP}.backup"
        cp .env ".env.${BACKUP_TIMESTAMP}.backup"
        log "Downloading latest .env.docker.example..."
        curl -fsSL "$REPO_URL/.env.docker.example" -o .env
        warn "Review your .env file - your previous settings are in .env.${BACKUP_TIMESTAMP}.backup"
    fi
    
    # Pull latest image
    log "Pulling latest image..."
    $COMPOSE_CMD pull
    
    # Restart if running
    if is_running; then
        log "Restarting containers..."
        $COMPOSE_CMD down
        $COMPOSE_CMD up -d
    else
        log "Containers not running. Start with './mgd.sh up'"
    fi
    
    log "Update complete!"
}

# Self-update the mgd.sh script
selfupdate() {
    log "Updating mgd.sh script..."
    curl -fsSL "$REPO_URL/mgd.sh" -o "mgd.sh.new"
    if [ -f "mgd.sh.new" ]; then
        mv mgd.sh.new mgd.sh
        chmod +x mgd.sh
        log "mgd.sh updated successfully!"
    else
        error "Failed to download mgd.sh"
    fi
}

# Up - create and start containers
up() {
    check_requirements
    require_compose_file
    log "Creating and starting containers..."
    $COMPOSE_CMD up -d
    log "Started!"
    
    # Show URL
    HTTP_PORT=$(grep -E "^HTTP_PORT=" .env 2>/dev/null | cut -d'=' -f2 || echo "8080")
    HTTP_PORT=${HTTP_PORT:-8080}
    log "Access the application at: http://localhost:${HTTP_PORT}"
}

# Down - stop and remove containers
down() {
    check_requirements
    require_compose_file
    log "Stopping and removing containers..."
    $COMPOSE_CMD down
    log "Stopped and removed!"
}

# Start existing containers
start() {
    check_requirements
    require_compose_file
    log "Starting containers..."
    $COMPOSE_CMD start
    log "Started!"
    
    # Show URL
    HTTP_PORT=$(grep -E "^HTTP_PORT=" .env 2>/dev/null | cut -d'=' -f2 || echo "8080")
    HTTP_PORT=${HTTP_PORT:-8080}
    log "Access the application at: http://localhost:${HTTP_PORT}"
}

# Stop containers (without removing)
stop() {
    check_requirements
    require_compose_file
    log "Stopping containers..."
    $COMPOSE_CMD stop
    log "Stopped!"
}

# View logs
logs() {
    check_requirements
    require_compose_file
    $COMPOSE_CMD logs -f
}

# Fix permissions command
fixperms() {
    DATA_PATH=$(get_data_path)
    if [ ! -d "$DATA_PATH" ]; then
        error "Data directory '$DATA_PATH' does not exist. Run './mgd.sh install' first."
    fi
    do_fix_permissions "$DATA_PATH"
}

# Main
case "${1:-}" in
    install)
        install
        ;;
    update)
        update
        ;;
    up)
        up
        ;;
    down)
        down
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
    fixperms)
        fixperms
        ;;
    selfupdate)
        selfupdate
        ;;
    *)
        show_help
        ;;
esac
