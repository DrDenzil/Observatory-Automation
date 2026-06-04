#!/usr/bin/env bash
set -euo pipefail

# EKOS Runner Deployment Script for Telescope Machines
# Run this script on a fresh telescope machine to set up the automation pipeline
# 
# Usage:
#   ./deploy.sh --machine-id scope01
#   ./deploy.sh --machine-id scope01 --ssh-key-file ~/.ssh/id_rsa_star
#   ./deploy.sh --machine-id scope01 --install-cron
#
# This script does NOT need to run on star-server

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SSH_KEY_FILE="${HOME}/.ssh/id_rsa_star"
INSTALL_DIR="${HOME}/.ekos-runner"
STAR_HOST="star-server"
STAR_USER="ds"
INSTALL_CRON=false
INSTALL_SYSTEMD=false
SKIP_DEPS=false
INSTALL_WEATHER=false
INSTALL_LX200GPS=false
INSTALL_WATCHDOG=false
WEATHER_STATION_HOST="147.197.130.103"
WEATHER_STATION_PORT="7332"

usage() {
    cat <<EOF
EKOS Runner Deployment Script

Usage: $0 [OPTIONS]

Options:
    --machine-id ID       Telescope machine ID (required, e.g. scope01)
    --ssh-key FILE       Path to SSH private key (default: ~/.ssh/id_rsa_star)
    --skip-deps          Skip dependency installation
    --install-cron       Install cron jobs for automated processing
    --install-weather    Install INDI weather safety proxy and weather script
    --install-lx200gps  Install LX200GPS initialization script (for Profile Editor)
    --install-watchdog  Install weather watchdog for emergency shutdown
    -h, --help           Show this help

Examples:
    $0 --machine-id scope01
    $0 --machine-id scope03 --install-cron --install-weather
    $0 --machine-id scope05 --install-weather
    $0 --machine-id scope05 --install-lx200gps
    $0 --machine-id scope05 --install-weather --install-lx200gps

The script will:
1. Install required dependencies (python3, python3-astropy, curl, rsync, sshpass)
2. Optionally install KStars/INDI natively (not Flatpak) and weather safety
3. Create directory structure under /var/lib/ekos-runner/jobs
4. Copy runner scripts to /home/\$USER/.ekos-runner/
5. Set up SSH key authentication for star-server
6. Optionally install cron jobs for automated processing
EOF
}

while [[ $# -gt 0 ]]; do
    case $1 in
        --machine-id)
            MACHINE_ID="$2"
            shift 2
            ;;
        --ssh-key-file)
            SSH_KEY_FILE="$2"
            shift 2
            ;;
        --skip-deps)
            SKIP_DEPS=true
            shift
            ;;
        --install-cron)
            INSTALL_CRON=true
            shift
            ;;
        --install-weather)
            INSTALL_WEATHER=true
            shift
            ;;
        --install-lx200gps)
            INSTALL_LX200GPS=true
            shift
            ;;
        --install-watchdog)
            INSTALL_WATCHDOG=true
            shift
            ;;
        --install-systemd)
            INSTALL_SYSTEMD=true
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            usage
            exit 1
            ;;
    esac
done

if [[ -z "${MACHINE_ID:-}" ]]; then
    echo "ERROR: --machine-id is required"
    usage
    exit 1
fi

log() {
    echo "[$(date -Iseconds)] [DEPLOY] $*"
}

error() {
    echo "[$(date -Iseconds)] [ERROR] $*" >&2
}

# =============================================================================
# STEP 1: Install Dependencies
# =============================================================================
install_dependencies() {
    log "Installing dependencies..."
    
    if [[ "$SKIP_DEPS" == "true" ]]; then
        log "Skipping dependency installation (--skip-deps)"
        return
    fi
    
    if command -v apt-get &>/dev/null; then
        sudo apt-get update -qq
        sudo apt-get install -y -qq python3 python3-astropy curl rsync sshpass xdotool 2>/dev/null || \
            sudo apt-get install -y python3 python3-astropy curl rsync sshpass xdotool
        log "Dependencies installed successfully"
    else
        error "Unsupported package manager. This script supports Debian/Ubuntu."
        exit 1
    fi
}

# =============================================================================
# STEP 1b: Install KStars and INDI (Natively, not Flatpak)
# =============================================================================
install_kstars_indi() {
    if [[ "$INSTALL_WEATHER" != "true" ]]; then
        return
    fi
    
    log "Installing KStars and INDI (native, not Flatpak)..."
    
    # Remove Flatpak KStars if present
    if command -v flatpak &>/dev/null; then
        if flatpak list | grep -q "org.kde.kstars"; then
            log "Removing Flatpak KStars..."
            flatpak uninstall -y org.kde.kstars 2>/dev/null || true
        fi
    fi
    
    # Add INDI PPA
    log "Adding INDI PPA..."
    sudo add-apt-repository -y ppa:mutlaqja/ppa 2>/dev/null || \
        sudo apt-add-repository -y ppa:mutlaqja/ppa
    
    # Update and install
    log "Installing KStars and INDI..."
    sudo apt-get update -qq
    sudo apt-get install -y kstars indi-full libindi1 2>/dev/null || \
        sudo apt-get install -y kstars libindi1
    
    # Install weather safety proxy driver
    log "Installing INDI weather drivers..."
    sudo apt-get install -y indi-weather-safety-proxy 2>/dev/null || \
        log "Note: indi-weather-safety-proxy may need manual install"
    
    log "KStars and INDI installed"
}

# =============================================================================
# STEP 1c: Install Weather Safety Script
# =============================================================================
install_weather_script() {
    if [[ "$INSTALL_WEATHER" != "true" ]]; then
        return
    fi
    
    log "Installing weather safety script..."
    
    # Create INDI scripts directory
    sudo mkdir -p /usr/local/share/indi/scripts
    
    # Copy weather script
    if [[ -f "${SCRIPT_DIR}/weather_safety.py" ]]; then
        sudo cp "${SCRIPT_DIR}/weather_safety.py" /usr/local/share/indi/scripts/weather_status.p
        sudo chmod +x /usr/local/share/indi/scripts/weather_status.p
        log "Weather script installed: /usr/local/share/indi/scripts/weather_status.p"
    else
        error "weather_safety.py not found in ${SCRIPT_DIR}"
        error "Please copy weather_safety.py alongside deploy.sh"
    fi
    
    # Create INDI config for Weather Safety Proxy
    log "Configuring INDI Weather Safety Proxy..."
    mkdir -p "${HOME}/.indi"
    cat > "${HOME}/.indi/Weather Safety Proxy_config.xml" <<EOF
<INDIDriver>
<newSwitchVector device='Weather Safety Proxy' name='DEBUG'>
  <oneSwitch name='ENABLE'>Off</oneSwitch>
  <oneSwitch name='DISABLE'>On</oneSwitch>
</newSwitchVector>
<newNumberVector device='Weather Safety Proxy' name='POLLING_PERIOD'>
  <oneNumber name='PERIOD_MS'>1000</oneNumber>
</newNumberVector>
<newNumberVector device='Weather Safety Proxy' name='WEATHER_UPDATE'>
  <oneNumber name='PERIOD'>60</oneNumber>
</newNumberVector>
<newNumberVector device='Weather Safety Proxy' name='WEATHER_SAFETY'>
  <oneNumber name='MIN_OK'>0.9</oneNumber>
  <oneNumber name='MAX_OK'>1.1</oneNumber>
</newNumberVector>
<newTextVector device='Weather Safety Proxy' name='WEATHER_SAFETY_SCRIPTS'>
  <oneText name='WEATHER_SAFETY_SCRIPT'>/usr/local/share/indi/scripts/weather_status.p</oneText>
</newTextVector>
<newSwitchVector device='Weather Safety Proxy' name='SCRIPT_OR_CURL'>
  <oneSwitch name='Use script'>On</oneSwitch>
  <oneSwitch name='Use url'>Off</oneSwitch>
</newSwitchVector>
</INDIDriver>
EOF
    log "INDI config created"
    
    # Test weather script
    log "Testing weather script..."
    if /usr/local/share/indi/scripts/weather_status.p 2>/dev/null | grep -q "roof_status"; then
        log "Weather script: OK"
    else
        log "Warning: Weather script test failed"
    fi
    
    log "Weather safety installation complete"
}

# =============================================================================
# STEP 1d: Install LX200GPS Initialization Script
# =============================================================================
install_lx200gps_script() {
    if [[ "$INSTALL_LX200GPS" != "true" ]]; then
        return
    fi
    
    log "Installing LX200GPS initialization script..."
    
    # Install pyserial if not present
    if ! python3 -c "import serial" 2>/dev/null; then
        log "Installing pyserial..."
        sudo pip3 install pyserial --break-system-packages 2>/dev/null || \
            sudo apt-get install -y python3-serial
    fi
    
    # Create INDI scripts directory and copy the script
    sudo mkdir -p /usr/local/share/indi/scripts
    if [[ -f "${SCRIPT_DIR}/lx200gps_init.py" ]]; then
        sudo cp "${SCRIPT_DIR}/lx200gps_init.py" /usr/local/share/indi/scripts/
        sudo chmod +x /usr/local/share/indi/scripts/lx200gps_init.py
        log "LX200GPS init script installed: /usr/local/share/indi/scripts/lx200gps_init.py"
    else
        log "Note: lx200gps_init.py not found - install will be skipped"
        return
    fi
    
    # Find the correct serial port for LX200GPS
    SERIAL_PORT=""
    if ls /dev/serial/by-id/ 2>/dev/null | grep -q .; then
        SERIAL_PORT=$(ls /dev/serial/by-id/ | head -1)
        SERIAL_PORT="/dev/serial/by-id/${SERIAL_PORT}"
        log "Detected serial port: ${SERIAL_PORT}"
    else
        log "No serial device detected - using default /dev/ttyUSB0"
        SERIAL_PORT="/dev/ttyUSB0"
    fi
    
    # Create a wrapper script that auto-detects the port
    sudo tee /usr/local/share/indi/scripts/lx200gps_wrapper.sh > /dev/null <<'WRAPPER'
#!/bin/bash
# LX200GPS Initialization Wrapper
# Finds the FTDI serial port and runs lx200gps_init.py

PORT=""
for dev in /dev/serial/by-path/* /dev/serial/by-id/*; do
    if [[ -e "$dev" ]] && [[ "$dev" == *"FTDI"* ]] || [[ "$dev" == *"USB"* ]]; then
        PORT="$dev"
        break
    fi
done

# Fallback to common ports
if [[ -z "$PORT" ]]; then
    for p in /dev/ttyUSB0 /dev/ttyUSB1 /dev/ttyACM0; do
        if [[ -e "$p" ]]; then
            PORT="$p"
            break
        fi
    done
fi

if [[ -z "$PORT" ]]; then
    echo "No serial port found for LX200GPS"
    exit 1
fi

exec /usr/local/share/indi/scripts/lx200gps_init.py --port "$PORT" --verbose
WRAPPER
    sudo chmod +x /usr/local/share/indi/scripts/lx200gps_wrapper.sh
    log "LX200GPS wrapper script created: /usr/local/share/indi/scripts/lx200gps_wrapper.sh"
    
    log ""
    log "LX200GPS initialization installed!"
    log ""
    log "To configure in EKOS Profile Editor:"
    log "  1. Open KStars -> Ekos -> Profile Editor"
    log "  2. Create/edit your LX200GPS profile"
    log "  3. In Scripts section, add pre-driver script:"
    log "     /usr/local/share/indi/scripts/lx200gps_wrapper.sh"
    log "  4. Save profile - script will run before LX200GPS driver starts"
    log ""
}

# =============================================================================
# STEP 1e: Install Weather Watchdog
# =============================================================================
install_watchdog() {
    if [[ "$INSTALL_WATCHDOG" != "true" ]]; then
        return
    fi
    
    log "Installing Weather Watchdog..."
    
    # Ensure weather script is installed
    if [[ ! -f "${SCRIPT_DIR}/weather_safety.py" ]]; then
        error "weather_safety.py not found"
        return
    fi
    
    sudo mkdir -p /usr/local/share/indi/scripts
    sudo cp "${SCRIPT_DIR}/weather_safety.py" /usr/local/share/indi/scripts/weather_status.p
    sudo chmod +x /usr/local/share/indi/scripts/weather_status.p
    log "Weather script installed"
    
    mkdir -p "${INSTALL_DIR}"
    
    # Copy ALL core automation scripts
    local core_scripts=(
        "pull_jobs.sh"
        "ekos_runner.py"
        "push_jobs.sh"
        "load_scheduler.sh"
        "emergency-shutdown.sh"
        "weather-watchdog.sh"
    )
    
    for script in "${core_scripts[@]}"; do
        if [[ -f "${SCRIPT_DIR}/${script}" ]]; then
            cp -f "${SCRIPT_DIR}/${script}" "${INSTALL_DIR}/"
            chmod +x "${INSTALL_DIR}/${script}"
            log "Installed: ${script}"
        fi
    done
    
    # Copy any Python scripts
    if [[ -f "${SCRIPT_DIR}/lx200gps_init.py" ]]; then
        cp -f "${SCRIPT_DIR}/lx200gps_init.py" "${INSTALL_DIR}/"
        chmod +x "${INSTALL_DIR}/lx200gps_init.py"
        log "Installed: lx200gps_init.py"
    fi
    
    # Install systemd service
    if [[ -f "${SCRIPT_DIR}/weather-watchdog.service" ]]; then
        log "Installing systemd service..."
        mkdir -p "${HOME}/.config/systemd/user"
        cp "${SCRIPT_DIR}/weather-watchdog.service" "${HOME}/.config/systemd/user/"
        systemctl --user daemon-reload
        log "Watchdog user service installed"
        log ""
        log "Enable/start after supervised hardware checks:"
        log "  systemctl --user enable weather-watchdog.service"
        log "  systemctl --user start weather-watchdog.service"
        log ""
        log "To check status:"
        log "  systemctl --user status weather-watchdog.service"
        log "  journalctl --user -u weather-watchdog.service -f"
    fi
    
    log ""
    log "Weather Watchdog installed!"
    log ""
    log "The watchdog will:"
    log "  1. Check weather every 30 seconds"
    log "  2. If unsafe for 2 consecutive checks, trigger emergency shutdown"
    log "  3. Emergency shutdown: parks dome, telescope, disables cooler"
    log ""
}

# =============================================================================
# STEP 2: Create Directory Structure for ALL scopes (01-09)
# =============================================================================
create_directories() {
    log "Creating directory structure for all scopes..."
    
    RUNNER_BASE="/var/lib/ekos-runner/jobs"
    
    for i in 01 02 03 04 05 06 07 08 09; do
        MACHINE_DIR="${RUNNER_BASE}/scope${i}"
        sudo mkdir -p "${MACHINE_DIR}"/{incoming,claimed,completed,failed,logs,generated,captures}
        log "  scope${i}: ${MACHINE_DIR}"
    done
    
    sudo chown -R "$(whoami):" "${RUNNER_BASE}"
    log "Directory structure complete"
}

# =============================================================================
# STEP 3: Copy Scripts
# =============================================================================
copy_scripts() {
    log "Copying runner scripts..."
    
    mkdir -p "${INSTALL_DIR}"
    
    # Copy all scripts from deploy directory
    if [[ -d "${SCRIPT_DIR}" ]]; then
        cp -f "${SCRIPT_DIR}"/*.sh "${INSTALL_DIR}/" 2>/dev/null || true
        cp -f "${SCRIPT_DIR}"/*.py "${INSTALL_DIR}/" 2>/dev/null || true
        # Copy scripts subdirectory if it exists
        if [[ -d "${SCRIPT_DIR}/scripts" ]]; then
            cp -f "${SCRIPT_DIR}/scripts/"*.sh "${INSTALL_DIR}/scripts/" 2>/dev/null || true
            mkdir -p "${INSTALL_DIR}/scripts"
            cp -f "${SCRIPT_DIR}/scripts/"*.sh "${INSTALL_DIR}/scripts/"
            chmod +x "${INSTALL_DIR}/scripts/"*.sh
        fi
        chmod +x "${INSTALL_DIR}"/*.sh
        chmod +x "${INSTALL_DIR}"/*.py
        log "Scripts copied to ${INSTALL_DIR}"
    else
        error "Runner scripts not found in ${SCRIPT_DIR}"
        error "Copy scripts to this directory first"
        exit 1
    fi
}

# =============================================================================
# STEP 4: Set Up SSH Key for Star-Server
# =============================================================================
setup_ssh_key() {
    log "Setting up SSH key for ${STAR_USER}@${STAR_HOST}..."
    
    SSH_DIR="${HOME}/.ssh"
    SSH_KEY_PUB="${SSH_KEY_FILE}.pub"
    
    mkdir -p "${SSH_DIR}"
    chmod 700 "${SSH_DIR}"
    
    # Copy the shared SSH key (assumes key is bundled with deploy.sh or copied separately)
    if [[ ! -f "${SSH_KEY_FILE}" ]]; then
        error "SSH key not found: ${SSH_KEY_FILE}"
        error "Please copy id_rsa_star and id_rsa_star.pub alongside deploy.sh"
        exit 1
    fi
    
    if [[ ! -f "${SSH_KEY_PUB}" ]]; then
        error "Public key not found: ${SSH_KEY_PUB}"
        exit 1
    fi
    
    chmod 600 "${SSH_KEY_FILE}"
    chmod 644 "${SSH_KEY_PUB}"
    
    # Configure SSH to use this key for star-server without overwriting any
    # unrelated SSH configuration.
    SSH_CONFIG="${SSH_DIR}/config"
    touch "${SSH_CONFIG}"
    chmod 600 "${SSH_CONFIG}"
    cp "${SSH_CONFIG}" "${SSH_CONFIG}.bak.$(date +%Y%m%d%H%M%S)"
    awk -v host="${STAR_HOST}" '
        /^Host[[:space:]]+/ { skip = ($2 == host) }
        !skip { print }
    ' "${SSH_CONFIG}" > "${SSH_CONFIG}.tmp"
    mv "${SSH_CONFIG}.tmp" "${SSH_CONFIG}"

    cat >> "${SSH_CONFIG}" <<EOF
# EKOS Runner - ${MACHINE_ID}
Host ${STAR_HOST}
    HostName 147.197.221.254
    User ${STAR_USER}
    IdentityFile ${SSH_KEY_FILE}
    BatchMode yes
    StrictHostKeyChecking accept-new
EOF
    
    log "SSH config updated: ${SSH_CONFIG}"
    log "Key installed: ${SSH_KEY_FILE}"
}

# =============================================================================
# STEP 5: Test SSH Connection
# =============================================================================
test_connection() {
    log "Testing SSH connection to star-server..."
    
    if ssh -i "${SSH_KEY_FILE}" "${STAR_USER}@${STAR_HOST}" "echo 'SSH connection OK'" 2>/dev/null; then
        log "SSH connection successful"
        return 0
    else
        error "Cannot connect to ${STAR_USER}@${STAR_HOST}"
        error "Please add the SSH public key to star-server manually"
        return 1
    fi
}

# =============================================================================
# STEP 6: Test rsync Access
# =============================================================================
test_rsync() {
    log "Testing rsync access to star-server..."
    
    if rsync -e "ssh -i ${SSH_KEY_FILE}" --dry-run "${STAR_USER}@${STAR_HOST}:/www/bayfordbury/automation/jobs/" /tmp/ &>/dev/null; then
        log "rsync access successful"
    else
        log "Warning: rsync test failed (may be normal if directory is empty)"
    fi
}

# =============================================================================
# STEP 7: Install Cron Jobs (Optional)
# =============================================================================
install_cron() {
    if [[ "$INSTALL_CRON" != "true" ]]; then
        log "Skipping cron installation (use --install-cron to enable)"
        return
    fi
    
    log "Installing cron jobs..."
    
    CRON_SCRIPT="${INSTALL_DIR}/run-automation.sh"
    
    cat > "${CRON_SCRIPT}" <<'CRONEOF'
#!/usr/bin/env bash
# EKOS Runner Automation Script - Called by cron
# Pulls jobs, processes them, and pushes results

set -euo pipefail

MACHINE_ID="${MACHINE_ID:-scope01}"
RUNNER_DIR="$(dirname "$(readlink -f "$0")")"
LOG_FILE="/var/log/ekos-runner-${MACHINE_ID}.log"

log() {
    echo "[$(date -Iseconds)] $*" | tee -a "${LOG_FILE}"
}

cd "${RUNNER_DIR}"

log "=== Starting EKOS automation cycle ==="

# Pull jobs from server
log "Pulling jobs..."
./pull_jobs.sh --machine-id "${MACHINE_ID}" >> "${LOG_FILE}" 2>&1 || true

# Process jobs with EKOS
log "Processing jobs..."
./ekos_runner.py --machine-id "${MACHINE_ID}" >> "${LOG_FILE}" 2>&1 || true

# Push results back
log "Pushing results..."
./push_jobs.sh --machine-id "${MACHINE_ID}" >> "${LOG_FILE}" 2>&1 || true

log "=== Automation cycle complete ==="
CRONEOF
    
    chmod +x "${CRON_SCRIPT}"
    
    # Create cron entry (runs every 2 hours)
    (crontab -l 2>/dev/null | grep -v "ekos-runner"; echo "0 */2 * * * MACHINE_ID=${MACHINE_ID} ${CRON_SCRIPT} >> /var/log/ekos-runner-${MACHINE_ID}.log 2>&1") | crontab -
    
    log "Cron jobs installed"
    log "Automation will run every 2 hours"
}

# =============================================================================
# STEP 8: Create Helper Scripts
# =============================================================================
create_helpers() {
    log "Creating helper scripts..."

    # copy_scripts installs the real monitored run.sh. Do not overwrite it with
    # a helper, or deployment will bypass scheduler loading and capture monitor.
    
    # Status check script
    cat > "${INSTALL_DIR}/status.sh" <<'HELPEREOF'
#!/usr/bin/env bash
# Check status of EKOS runner

MACHINE_ID="${1:-scope01}"
QUEUE_ROOT="/var/lib/ekos-runner/jobs"

echo "EKOS Runner Status for ${MACHINE_ID}"
echo "=================================="
echo ""

for dir in incoming claimed completed failed; do
    path="${QUEUE_ROOT}/${MACHINE_ID}/${dir}"
    count=$(find "${path}" -maxdepth 1 -name "*.json" 2>/dev/null | wc -l)
    echo "${dir}: ${count} jobs"
done

echo ""
echo "Last run: $(tail -1 /var/log/ekos-runner-${MACHINE_ID}.log 2>/dev/null || echo 'No log found')"
HELPEREOF
    chmod +x "${INSTALL_DIR}/status.sh"
    
    log "Helper scripts created"
}

# =============================================================================
# Create runtime environment file
# =============================================================================
create_runtime_env() {
    local env_file="${INSTALL_DIR}/ekos-runner.env"

    if [[ -f "${env_file}" ]]; then
        log "Keeping existing runtime environment: ${env_file}"
        return
    fi

    cat > "${env_file}" <<EOF
# Runtime configuration for EKOS Observatory Automation.
# Edit this file on the telescope machine before starting unattended service.

EKOS_PROFILE=${MACHINE_ID}
INDI_PORT=7624

# Set production hardware drivers for this scope before relying on crash recovery.
# Example format:
# INDI_DRIVERS="indi_planewave_telescope indi_simulator_ccd indi_weather_safety_proxy"
INDI_DRIVERS=""
EOF
    chmod 600 "${env_file}"
    log "Runtime environment created: ${env_file}"
}

# =============================================================================
# Install systemd service for continuous operation
# =============================================================================
install_systemd() {
    log "Installing systemd service for continuous operation..."
    
    TIMER_FILE="${SCRIPT_DIR}/ekos-runner.timer"
    
    if [[ ! -f "$TIMER_FILE" ]]; then
        error "Timer file not found in ${SCRIPT_DIR}"
        return 1
    fi
    
    # Generate service for this deployed machine. User services already run as
    # the logged-in user, so do not include User=/Group= here.
    mkdir -p ~/.config/systemd/user/
    cat > ~/.config/systemd/user/ekos-runner.service <<EOF
[Unit]
Description=EKOS Observatory Automation Runner (${MACHINE_ID})
After=graphical-session.target
Wants=ekos-runner.timer

[Service]
Type=simple
WorkingDirectory=${INSTALL_DIR}
ExecStart=${INSTALL_DIR}/run-continuous.sh ${MACHINE_ID}
EnvironmentFile=-${INSTALL_DIR}/ekos-runner.env
StandardOutput=journal
StandardError=journal
Restart=on-failure
RestartSec=60

[Install]
WantedBy=default.target
EOF
    cp "$TIMER_FILE" ~/.config/systemd/user/
    
    # Reload systemd
    systemctl --user daemon-reload
    
    # Do not enable/start automatically. Production machines should confirm
    # EKOS_PROFILE/INDI_DRIVERS and run a supervised test first.
    log "systemd service and timer installed"
    echo ""
    echo "Service status:"
    echo "  systemctl --user status ekos-runner.service"
    echo "  systemctl --user status ekos-runner.timer"
    echo ""
    echo "View logs:"
    echo "  journalctl --user -u ekos-runner.service -f"
    echo ""
    echo "Enable/start after supervised checks:"
    echo "  systemctl --user enable ekos-runner.timer"
    echo "  systemctl --user start ekos-runner.timer"
    echo ""
}

# =============================================================================
# MAIN
# =============================================================================
main() {
    echo ""
    echo "=============================================="
    echo "  EKOS Runner Deployment"
    echo "  Machine: ${MACHINE_ID}"
    echo "=============================================="
    echo ""
    
    install_dependencies
    
    if [[ "$INSTALL_WEATHER" == "true" ]]; then
        install_kstars_indi
        install_weather_script
    fi
    
    if [[ "$INSTALL_LX200GPS" == "true" ]]; then
        install_lx200gps_script
    fi
    
    if [[ "$INSTALL_WATCHDOG" == "true" ]]; then
        install_watchdog
    fi
    
    create_directories
    copy_scripts
    setup_ssh_key
    
    echo ""
    echo "Testing connection to star-server..."
    if ssh -o ConnectTimeout=5 -i "${SSH_KEY_FILE}" "${STAR_USER}@${STAR_HOST}" "echo 'SSH OK'" 2>/dev/null; then
        echo "  Connection: OK"
    else
        echo "  Connection: FAILED"
        echo "  Ensure the SSH key is authorized on star-server"
    fi
    
    create_helpers
    create_runtime_env
    install_cron
    
    echo ""
    echo "=============================================="
    echo "  Deployment Complete!"
    echo "=============================================="
    echo ""
    echo "Scripts installed to: ${INSTALL_DIR}/"
    echo ""
    
    if [[ "$INSTALL_WEATHER" == "true" ]]; then
        echo "Weather Safety installed:"
        echo "  Script: /usr/local/share/indi/scripts/weather_status.p"
        echo "  Driver: indi_weather_safety_proxy"
        echo ""
        echo "To use weather safety in EKOS:"
        echo "  1. Start KStars"
        echo "  2. Open EKOS (Ctrl+K)"
        echo "  3. Add Weather Safety Proxy device"
        echo "  4. Connect - should show 'All clear'"
        echo ""
    fi
    
    if [[ "$INSTALL_LX200GPS" == "true" ]]; then
        echo "LX200GPS Initialization installed:"
        echo "  Script: /usr/local/share/indi/scripts/lx200gps_init.py"
        echo "  Wrapper: /usr/local/share/indi/scripts/lx200gps_wrapper.sh"
        echo ""
        echo "Configure in EKOS Profile Editor:"
        echo "  1. Open Ekos -> Profile Editor"
        echo "  2. Edit your LX200GPS profile"
        echo "  3. Add pre-driver script:"
        echo "     /usr/local/share/indi/scripts/lx200gps_wrapper.sh"
        echo ""
    fi
    
    if [[ "$INSTALL_WATCHDOG" == "true" ]]; then
        echo "Weather Watchdog installed:"
        echo "  Script: ${INSTALL_DIR}/weather-watchdog.sh"
        echo "  Service: weather-watchdog.service"
        echo ""
        echo "The watchdog monitors weather and will:"
        echo "  - Park dome (close)"
        echo "  - Park telescope"
        echo "  - Disable cooler/heaters"
        echo "  - Update job status"
        echo ""
        echo "Start watchdog:"
        echo "  systemctl --user start weather-watchdog.service"
        echo ""
    fi
    
    if [[ "$INSTALL_SYSTEMD" == "true" ]]; then
        install_systemd
    fi
    
    echo "Manual test:"
    echo "  cd ${INSTALL_DIR}"
    echo "  ./run.sh ${MACHINE_ID}"
    echo ""
    echo "Install automated cron (optional):"
    echo "  ./deploy.sh --machine-id ${MACHINE_ID} --install-cron"
    echo ""
    echo "Install systemd service (recommended):"
    echo "  ./deploy.sh --machine-id ${MACHINE_ID} --install-systemd"
    echo ""
}

main
