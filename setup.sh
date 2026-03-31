#!/bin/bash
# Bolin EXU-230NX PTZ Controller — Setup Script
# Run once after cloning to configure directory permissions.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SNAP_DIR="$SCRIPT_DIR/snapshots"

# Detect web server user
if id apache &>/dev/null; then
    WEB_USER="apache"
elif id www-data &>/dev/null; then
    WEB_USER="www-data"
else
    echo "ERROR: Could not detect web server user (tried apache, www-data)."
    echo "       Set WEB_USER manually and re-run:"
    echo "       sudo chown <user>:<user> $SNAP_DIR"
    exit 1
fi

echo "Detected web server user: $WEB_USER"

# Create snapshots directory if it doesn't exist
mkdir -p "$SNAP_DIR"

# Set ownership and permissions
sudo chown "$WEB_USER:$WEB_USER" "$SNAP_DIR"
sudo chmod 755 "$SNAP_DIR"

echo "Done. Snapshots directory ready: $SNAP_DIR"
