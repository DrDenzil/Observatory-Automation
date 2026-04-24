#!/usr/bin/env bash
set -euo pipefail

# Load scheduler files into KStars via D-Bus
# Usage: ./load_scheduler.sh [--machine-id scope01] [--dry-run] [--no-start]

log() {
    echo "[$(date -Iseconds)] $*"
}

usage() {
    cat <<EOF
Usage: $0 [OPTIONS]

Options:
    --machine-id ID      Machine ID (default: scope06)
    --queue-root PATH    Local queue root (default: /var/lib/ekos-runner/jobs)
    --dry-run            Preview what would be loaded
    --no-start           Don't auto-start KStars if not running
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

wait_for_kstars() {
    local timeout="$1"
    local elapsed=0
    
    log "Waiting for KStars D-Bus interface..."
    while [[ $elapsed -lt $timeout ]]; do
        if dbus-send --session --dest="${KSTAR_DEST}" --print-reply "${SCHEDULER_PATH}" \
            org.freedesktop.DBus.Introspectable.Introspect 2>/dev/null | grep -q "Scheduler"; then
            log "KStars D-Bus ready"
            return 0
        fi
        sleep 1
        ((elapsed++))
    done
    
    log "ERROR: KStars D-Bus not available after ${timeout}s"
    return 1
}

ensure_kstars_running() {
    if dbus-send --session --dest="${KSTAR_DEST}" --print-reply "${SCHEDULER_PATH}" \
        org.freedesktop.DBus.Introspectable.Introspect 2>/dev/null | grep -q "Scheduler"; then
        log "KStars is already running"
        return 0
    fi
    
    if [[ "${NO_START}" == "true" ]]; then
        log "KStars not running (--no-start specified)"
        return 1
    fi
    
    log "KStars not running - starting..."
    
    if command -v flatpak &>/dev/null && flatpak info org.kde.kstars &>/dev/null; then
        log "Using Flatpak KStars"
        nohup flatpak run org.kde.kstars &>/dev/null &
    else
        log "Using native KStars"
        nohup kstars &>/dev/null &
    fi
    
    wait_for_kstars "${KSTAR_TIMEOUT}" || return 1
    log "KStars started successfully"
    return 0
}

GENERATED_PATH="${QUEUE_ROOT}/${MACHINE_ID}/generated"

if [[ ! -d "${GENERATED_PATH}" ]]; then
    log "No generated directory found: ${GENERATED_PATH}"
    exit 0
fi

if [[ "${DRY_RUN}" == "false" ]]; then
    ensure_kstars_running || exit 1
fi

ESL_FILES=()

if [[ -f "${GENERATED_PATH}/combined_scheduler.esl" ]]; then
    ESL_FILES=("${GENERATED_PATH}/combined_scheduler.esl")
    log "Using combined scheduler"
else
    while IFS= read -r -d '' file; do
        if [[ "$file" != *"combined_scheduler"* ]]; then
            ESL_FILES+=("$file")
        fi
    done < <(find "${GENERATED_PATH}" -maxdepth 2 -name "*.esl" -print0 2>/dev/null)
fi

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

if [[ "${DRY_RUN}" == "false" ]]; then
    log "Checking loaded jobs..."
    dbus-send --session --dest="${KSTAR_DEST}" --print-reply \
        "${SCHEDULER_PATH}" \
        org.freedesktop.DBus.Properties.Get \
        string:"org.kde.kstars.Ekos.Scheduler" \
        string:"jsonJobs" 2>/dev/null | grep -o '"name":"[^"]*"' || true
fi

log "Done"
