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

# Try each known driver; stop at the first that accepts the command.
# indi_setprop exits non-zero when the device is not connected, so the
# || chain naturally skips drivers that aren't loaded.
park_telescope() {
    local driver="$1"
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY-RUN] indi_setprop ${driver}.TELESCOPE_PARK.PARK=On"
        return 0
    fi
    indi_setprop -h "$INDI_HOST" -p "$INDI_PORT" "${driver}.TELESCOPE_PARK.PARK=On" 2>/dev/null
}

# Poll TELESCOPE_PARK_STATUS until the mount confirms it is at the park
# position, rather than declaring success immediately after the command.
wait_for_park() {
    local device="$1"
    local timeout="${2:-120}"

    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY-RUN] Would wait up to ${timeout}s for ${device} to park"
        return 0
    fi

    local interval=5
    local elapsed=0
    log "Waiting up to ${timeout}s for ${device} to reach park position..."
    while [[ $elapsed -lt $timeout ]]; do
        local status
        status=$(indi_getprop -h "$INDI_HOST" -p "$INDI_PORT" \
            "${device}.TELESCOPE_PARK_STATUS.TELESCOPE_PARKED" 2>/dev/null || true)
        if echo "$status" | grep -q "TELESCOPE_PARKED=On"; then
            log "${device} confirmed at park position"
            return 0
        fi
        sleep $interval
        elapsed=$((elapsed + interval))
    done
    log "Warning: ${device} park not confirmed within ${timeout}s — verify position manually before proceeding"
    return 1
}

PARKED_TELESCOPE=""
for driver in "Planewave Telescope" "EQMod Mount" "LX200 GPS" "Telescope Simulator"; do
    if park_telescope "$driver"; then
        PARKED_TELESCOPE="$driver"
        break
    fi
done

if [[ -n "$PARKED_TELESCOPE" ]]; then
    wait_for_park "$PARKED_TELESCOPE" 120 || true
else
    log "Warning: Could not park telescope (may not be connected)"
fi
log "Telescope park sequence complete"


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
