#!/usr/bin/env bash
set -uo pipefail

# Load scheduler files into KStars via D-Bus
# Usage: ./load_scheduler.sh [--machine-id scope01] [--dry-run] [--no-start]

log() {
    echo "[$(date -Iseconds)] $*"
}

# Load notification helper
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/scripts/notify.sh" 2>/dev/null || notify() { echo "Notify: $1 - $2"; }

# Notify error and exit
error_exit() {
    notify "Scheduler Error" "Machine ${MACHINE_ID:-unknown}: $1"
    log "ERROR: $1"
    exit 1
}

usage() {
    cat <<EOF
Usage: $0 [OPTIONS]

Options:
    --machine-id ID      Machine ID (default: scope06)
    --queue-root PATH    Local queue root (default: /var/lib/ekos-runner/jobs)
    --dry-run            Preview what would be loaded
    --no-start           Don't auto-start KStars if not running
    --no-auto-start     Don't auto-start scheduler after loading
    -h, --help           Show this help

Environment:
    KSTAR_DEST           DBus destination (default: org.kde.kstars)
    SCHEDULER_PATH       D-Bus scheduler path (default: /KStars/Ekos/Scheduler)
    KSTAR_TIMEOUT        Seconds to wait for KStars D-Bus (default: 30)

Example:
    $0 --machine-id scope01
    $0 --machine-id scope01 --dry-run
EOF
}

MACHINE_ID="${MACHINE_ID:-scope06}"
QUEUE_ROOT="${QUEUE_ROOT:-/var/lib/ekos-runner/jobs}"
DRY_RUN=false
NO_START=false
KSTAR_DEST="${KSTAR_DEST:-org.kde.kstars}"
SCHEDULER_PATH="/KStars/Ekos/Scheduler"
KSTAR_TIMEOUT="${KSTAR_TIMEOUT:-30}"

while [[ $# -gt 0 ]]; do
    case $1 in
        --machine-id)
            MACHINE_ID="$2"
            shift 2
            ;;
        --queue-root)
            QUEUE_ROOT="$2"
            shift 2
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --no-start)
            NO_START=true
            shift
            ;;
        --no-auto-start)
            NO_AUTO_START=true
            shift
            ;;
        -h|--help)
            usage
            error_exit "Help requested"
            ;;
        *)
            echo "Unknown option: $1"
            usage
            error_exit "Unknown option: $1"
            ;;

# Check args
if [[ -z "${MACHINE_ID:-}" ]]; then
    error_exit "--machine-id is required"
fi

# Check if dry run
if [[ "${DRY_RUN:-}" == "true" ]]; then
    log "Dry run - no changes will be made"
    exit 0
fi

# Find queue root
QUEUE_ROOT="/var/lib/ekos-runner/jobs/${MACHINE_ID}"
mkdir -p "${QUEUE_ROOT}"/{incoming,claimed,completed,failed,generated}

# Check if KStars is running
ensure_kstars_running || error_exit "KStars not running"
    
    # Verify jobs are cleared
    sleep 1
    job_count=$(dbus-send --session --dest="${KSTAR_DEST}" --print-reply \
        "${SCHEDULER_PATH}" \
        org.freedesktop.DBus.Properties.Get \
        string:"org.kde.kstars.Ekos.Scheduler" \
        string:"jsonJobs" 2>/dev/null | grep -o '"name":"' | wc -l || echo 0)
    job_count="${job_count//[[:space:]]/}"  # Remove any whitespace
    if [[ "${job_count:-0}" -gt 0 ]]; then
        log "WARNING: Jobs not fully cleared (count: $job_count), forcing remove..."
        dbus-send --session --dest="${KSTAR_DEST}" --print-reply \
            "${SCHEDULER_PATH}" \
            org.kde.kstars.Ekos.Scheduler.removeAllJobs 2>/dev/null || true
    fi
fi

ESL_FILES=()

if [[ -f "${GENERATED_PATH}/combined_scheduler.esl" ]]; then
    ESL_FILES=("${GENERATED_PATH}/combined_scheduler.esl")
    log "Using combined scheduler"
else
    # Find the oldest job folder that hasn't been processed yet (no captures AND not already pushed)
    # Sort by name to get FIFO order (oldest job first)
    for dir in $(ls -1d "${GENERATED_PATH}"/*/ 2>/dev/null | sort); do
        dir_name=$(basename "$dir")
        captures_count=$(ls "$dir/captures/"*.fits 2>/dev/null | wc -l)
        
        # Skip if already pushed (has .pushed marker)
        if [[ -f "$dir/.pushed" ]]; then
            continue
        fi
        
        if [[ "$captures_count" -eq 0 ]]; then
            # Found a job without captures - this is our next job
            latest_esl=$(find "$dir" -maxdepth 1 -name "*.esl" -not -name "*combined*" 2>/dev/null | head -1)
            if [[ -n "$latest_esl" ]]; then
                ESL_FILES=("$latest_esl")
                log "Using next pending scheduler: ${latest_esl}"
                break
            fi
        fi
    done
fi

log "ESL_FILES count: ${#ESL_FILES[@]}"
if [[ ${#ESL_FILES[@]} -eq 0 ]]; then
    log "No .esl files found in ${GENERATED_PATH}"
    exit 0
fi

for esl_file in "${ESL_FILES[@]}"; do
    log "Scheduler file: ${esl_file}"
    
    if [[ "${DRY_RUN}" == "true" ]]; then
        log "[DRY-RUN] Would load: ${esl_file}"
    else
        log "Loading scheduler into KStars..."
        result=$(dbus-send --session --dest="${KSTAR_DEST}" --print-reply \
            "${SCHEDULER_PATH}" \
            org.kde.kstars.Ekos.Scheduler.loadScheduler \
            "string:${esl_file}" 2>&1)
        
        if echo "$result" | grep -q "boolean true"; then
            log "OK: Loaded ${esl_file}"
        else
            log "ERROR: Failed to load ${esl_file}"
            log "Result: ${result}"
        fi
fi
 done

# Auto-start scheduler after loading (unless --no-auto-start is set)
if [[ "${DRY_RUN}" == "false" ]] && [[ "${NO_AUTO_START:-false}" != "true" ]]; then
    log "Auto-starting scheduler..."
    dbus-send --session --dest="${KSTAR_DEST}" --print-reply \
        "${SCHEDULER_PATH}" \
        org.kde.kstars.Ekos.Scheduler.start 2>/dev/null || true
    sleep 2
    
    # Verify it started - status 2 = running/capturing
    status=$(dbus-send --session --dest="${KSTAR_DEST}" --print-reply \
        "${SCHEDULER_PATH}" \
        org.freedesktop.DBus.Properties.Get \
        string:"org.kde.kstars.Ekos.Scheduler" \
        string:"status" 2>/dev/null | grep -o 'int32 [0-9]' | awk '{print $2}')
    
    if [[ "$status" == "2" ]] || [[ "$status" == "0" ]]; then
        log "Scheduler started successfully (status: running)"
    else
        log "WARNING: Scheduler may not have started (status: $status)"
    fi
fi

if [[ "${DRY_RUN}" == "false" ]]; then
    log "Checking loaded jobs..."
    dbus-send --session --dest="${KSTAR_DEST}" --print-reply \
        "${SCHEDULER_PATH}" \
        org.freedesktop.DBus.Properties.Get \
        string:"org.kde.kstars.Ekos.Scheduler" \
        string:"jsonJobs" 2>/dev/null | grep -o '"name":"[^"]*"' || true
fi

log "Done"
