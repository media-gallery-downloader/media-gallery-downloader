#!/usr/bin/env bash
# Bump version in config/app.php based on git tags
# Usage: ./scripts/bump-version.sh [version]
# If no version provided, uses the latest git tag

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
CONFIG_FILE="$PROJECT_DIR/config/app.php"

# Get version from argument or latest tag
if [ -n "$1" ]; then
    VERSION="$1"
else
    # Get latest tag, strip 'v' prefix
    VERSION=$(git describe --tags --abbrev=0 2>/dev/null | sed 's/^v//')
    if [ -z "$VERSION" ]; then
        echo "Error: No version provided and no git tags found"
        exit 1
    fi
fi

# Validate version format (basic semver check)
if ! echo "$VERSION" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+'; then
    echo "Error: Invalid version format: $VERSION (expected x.y.z)"
    exit 1
fi

echo "Updating version to: $VERSION"

# Update config/app.php
if [ -f "$CONFIG_FILE" ]; then
    # Use sed to replace the version line
    sed -i "s/'version' => '[^']*'/'version' => '$VERSION'/" "$CONFIG_FILE"
    echo "Updated $CONFIG_FILE"
else
    echo "Error: Config file not found: $CONFIG_FILE"
    exit 1
fi

# Show the change
grep "'version'" "$CONFIG_FILE"
