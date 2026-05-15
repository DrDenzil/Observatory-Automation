#!/bin/bash
#
# Mininet Lab Shortcut Script
# Brings up the Docker container and opens a terminal for student use
#

echo "Starting Mininet container..."

# Change to the docker directory
cd /home/astro/docker/mininet

# Start the container if not running
docker compose up -d

# Wait for container to be ready
sleep 2

# Check if container is running
if docker ps | grep -q mininet-lab; then
    echo "Container is running. Opening terminal..."
    
    # Open a terminal exec'd into the container
    # Detects available terminal emulator
    if command -v gnome-terminal &> /dev/null; then
        gnome-terminal -x docker exec -it mininet-lab bash
    elif command -v konsole &> /dev/null; then
        konsole -e docker exec -it mininet-lab bash
    elif command -v xfce4-terminal &> /dev/null; then
        xfce4-terminal -x docker exec -it mininet-lab bash
    elif command -v xterm &> /dev/null; then
        xterm -e docker exec -it mininet-lab bash
    elif command -v mate-terminal &> /dev/null; then
        mate-terminal -e docker exec -it mininet-lab bash
    else
        # Fallback - just exec directly (will use default terminal)
        docker exec -it mininet-lab bash
    fi
else
    echo "Error: Container failed to start"
    exit 1
fi