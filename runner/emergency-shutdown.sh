#!/usr/bin/env bash
set -euo pipefail

# Emergency Observatory Shutdown Script
# Safely closes dome, parks equipment, and updates job status
#
# Usage:
#   ./emergency-shutdown.sh                    # Normal emergency shutdown
#   ./emergency-shutdown.sh --reason "Rain detected"
#   ./emergency-shutdown.sh --weather         # Called by weather watchdog
#   ./emergency-shutdown.sh --watchdog         # Called by watchdog timeout
#   ./emergency-shutdown.sh --normal           # Normal end-of-session shutdown
#
# Can be called multiple times safely (idempotent)

log() {
    echo "[$(date -Iseconds)] [SHUTDOWN] $*"
}

error() {
    echo "[$(date -Iseconds)] [SHUTDOWN ERROR] $*" >&2
}

usage() {
    cat <<EOF
Emergency Observatory Shutdown

Usage: $0 [OPTIONS]

Options:
    --reason TEXT     Reason for shutdown (for logging)
    --weather         Shutdown due to weather (sets appropriate status)
    --watchdog        Shutdown due to watchdog timeout
    --normal          Normal end-of-session shutdown
    --dry-run         Preview actions without executing
    -h, --help        Show this help

Environment:
    INDI_HOST         INDI server host (default: localhost)
    INDI_PORT         INDI server port (default: 7624)
    KSTAR_DEST        KStars DBus destination (default: org.kde.kstars)
    JOB_ID            Job ID to update status for
    SSH_KEY           SSH key for server communication

EOF
}

REASON="${REASON:-Emergency shutdown}"
SHUTDOWN_TYPE="EMERGENCY"
DRY_RUN=false
INDI_HOST="${INDI_HOST:-localhost}"
INDI_PORT="${INDI_PORT:-7624}"
KSTAR_DEST="${KSTAR_DEST:-org.kde.kstars}"
JOB_ID="${JOB_ID:-}"
SSH_KEY="${SSH_KEY:-${HOME}/.ssh/id_rsa_star}"
STAR_HOST="${STAR_HOST:-star-server}"
STAR_USER="${STAR_USER:-ds}"

while [[ $# -gt 0 ]]; do
    case $1 in
        --reason)
            REASON="$2"
            shift 2
            ;;
        --weather)
            SHUTDOWN_TYPE="WEATHER"
            REASON="Weather unsafe - $(date -Iseconds)"
            shift
            ;;
        --watchdog)
            SHUTDOWN_TYPE="WATCHDOG"
            REASON="Watchdog timeout - $(date -Iseconds)"
            shift
            ;;
        --normal)
            SHUTDOWN_TYPE="NORMAL"
            REASON="End of session - $(date -Iseconds)"
            shift
            ;;
        --dry-run)
            DRY_RUN=true
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

log "=========================================="
log "  Emergency Shutdown Starting"
log "  Type: ${SHUTDOWN_TYPE}"
log "  Reason: ${REASON}"
log "=========================================="

if [[ "$DRY_RUN" == "true" ]]; then
    log "[DRY-RUN] Would perform emergency shutdown"
fi

indie() {
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY-RUN] indi_setprop $*"
    else
        indi_setprop -h "$INDI_HOST" -p "$INDI_PORT" "$@" 2>/dev/null || true
    fi
}

dbus_call() {
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY-RUN] dbus-send $*"
    else
        dbus-send --session --dest="${KSTAR_DEST}" "$@" 2>/dev/null || true
    fi
}

STEP=0
TOTAL=6

# =============================================================================
# STEP 1: Abort EKOS Scheduler
# =============================================================================
STEP=$((STEP + 1))
log "[${STEP}/${TOTAL}] Aborting EKOS scheduler..."

if [[ "$DRY_RUN" == "false" ]]; then
    dbus_call \
        /KStars/Ekos/Scheduler \
        org.kde.kstars.Ekos.Scheduler.abortAll 2>/dev/null || true
    
    sleep 2
fi
log "Scheduler aborted"


# =============================================================================
# STEP 2: Park Dome (Close Shutter)
# =============================================================================
STEP=$((STEP + 1))
log "[${STEP}/${TOTAL}] Parking dome (closing shutter)..."

if [[ "$DRY_RUN" == "false" ]]; then
    indie "Dome Simulator.DOME_PARK.PARK=On" 2>/dev/null || \
    indie "DDW Dome.DOME_PARK.PARK=On" 2>/dev/null || \
    indie "NexDome.DOME_PARK.PARK=On" 2>/dev/null || \
    log "Warning: Could not park dome (may not be connected)"
    
    sleep 5
fi
log "Dome parked/closed"


# =============================================================================
# STEP 3: Park Telescope
# =============================================================================
STEP=$((STEP + 1))
log "[${STEP}/${TOTAL}] Parking telescope..."

if [[ "$DRY_RUN" == "false" ]]; then
    indie "Telescope Simulator.TELESCOPE_PARK.PARK=On" 2>/dev/null || \
    indie "LX200 GPS.TELESCOPE_PARK.PARK=On" 2>/dev/null || \
    indie "EQMod Mount.TELESCOPE_PARK.PARK=On" 2>/dev/null || \
    log "Warning: Could not park telescope (may not be connected)"
    
    sleep 3
fi
log "Telescope parked"


# =============================================================================
# STEP 4: Disable Cooler / Heaters
# =============================================================================
STEP=$((STEP + 1))
log "[${STEP}/${TOTAL}] Disabling cooler and heaters..."

if [[ "$DRY_RUN" == "false" ]]; then
    indie "CCD Simulator.CCD_COOLER_POWER.COOLER_ON=Off" 2>/dev/null || true
    indie "CCD Simulator.DEW_HEATER.DEW_HEATER=Off" 2>/dev/null || true
    
    indie "ASCOM Camera.CCD_COOLER_POWER.COOLER_ON=Off" 2>/dev/null || true
    indie "ZWO Camera.CCD_COOLER_POWER.COOLER_ON=Off" 2>/dev/null || true
    indie "QHY Camera.CCD_COOLER_POWER.COOLER_ON=Off" 2>/dev/null || true
fi
log "Cooler/heaters disabled"


# =============================================================================
# STEP 5: Update Job Status
# =============================================================================
STEP=$((STEP + 1))
log "[${STEP}/${TOTAL}] Updating job status..."

if [[ -n "$JOB_ID" ]]; then
    if [[ "$DRY_RUN" == "false" ]]; then
        UPDATE_SCRIPT="$(dirname "${BASH_SOURCE[0]}")/update_rtml_status.php"
        if [[ -f "$UPDATE_SCRIPT" ]]; then
            STATUS=""
            case "$SHUTDOWN_TYPE" in
                WEATHER)
                    STATUS="INTERRUPTED_WEATHER"
                    ;;
                WATCHDOG)
                    STATUS="INTERRUPTED_TIMEOUT"
                    ;;
                NORMAL)
                    STATUS="COMPLETED"
                    ;;
                *)
                    STATUS="INTERRUPTED"
                    ;;
            esac
            
            php "$UPDATE_SCRIPT" --job-id "$JOB_ID" --status "$STATUS" 2>/dev/null || \
                log "Warning: Could not update job status"
        else
            log "Note: update_rtml_status.php not found"
        fi
    fi
    log "Job ${JOB_ID} status updated"
else
    log "No JOB_ID set, skipping job status update"
fi


# =============================================================================
# STEP 6: Final Logging
# =============================================================================
STEP=$((STEP + 1))
log "[${STEP}/${TOTAL}] Finalizing..."

LOG_FILE="/var/log/observatory-shutdown.log"
mkdir -p "$(dirname "$LOG_FILE")" 2>/dev/null || true

if [[ "$DRY_RUN" == "false" ]]; then
    echo "[$(date -Iseconds)] ${SHUTDOWN_TYPE} | ${REASON}" >> "$LOG_FILE" 2>/dev/null || true
fi

log "=========================================="
log "  Emergency Shutdown Complete"
log "  Time: $(date -Iseconds)"
log "=========================================="

exit 0
