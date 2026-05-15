#!/bin/bash
#
# Mininet Lab Setup Script
# Run this to set up the lab environment
#

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOME_DIR="$HOME"

echo "=== Mininet Lab Setup ==="
echo ""

# Check if running from repo folder
if [ -f "$SCRIPT_DIR/mininet.dockerfile" ]; then
    echo "Local setup detected..."
    REPO_DIR="$SCRIPT_DIR"
else
    echo "No local files found."
    echo "Enter the GitHub repository URL:"
    echo "Example: https://github.com/DrDenzil/Mininet-Lab.git"
    read -r REPO_URL

    if [ -z "$REPO_URL" ]; then
        echo "Error: Repository URL required"
        exit 1
    fi

    echo "Cloning repository..."
    REPO_DIR="$HOME_DIR/docker/mininet"
    mkdir -p "$REPO_DIR"
    git clone "$REPO_URL" "$REPO_DIR"
fi

# Create directories
echo "Creating directories..."
mkdir -p "$HOME_DIR/docker/mininet"
mkdir -p "$HOME_DIR/AppData/mininet"

# Copy docker files
echo "Copying docker files..."
cp "$REPO_DIR/"* "$HOME_DIR/docker/mininet/" 2>/dev/null || true

# Copy lab files
echo "Copying lab files..."
cp "$REPO_DIR/Lab"*.py "$HOME_DIR/AppData/mininet/" 2>/dev/null || true

# Copy desktop shortcut to Desktop
echo "Adding desktop shortcut..."
mkdir -p "$HOME_DIR/Desktop"
cp "$REPO_DIR/Mininet-Lab.desktop" "$HOME_DIR/Desktop/" 2>/dev/null || true
chmod +x "$HOME_DIR/Desktop/Mininet-Lab.desktop" 2>/dev/null || true

# Make scripts executable
chmod +x "$HOME_DIR/docker/mininet/"*.sh 2>/dev/null || true

echo ""
echo "=== Setup Complete ==="
echo ""
echo "Double-click Mininet-Lab.desktop on your Desktop to start!"
echo ""
echo "Or in terminal:"
echo "  cd $HOME_DIR/docker/mininet"
echo "  docker compose up -d"
echo "  docker exec -it mininet-lab bash"
echo ""
echo "Lab files: $HOME_DIR/AppData/mininet/"
echo ""
echo "Windows users: Run setup inside WSL2 (Ubuntu), not CMD/PowerShell"