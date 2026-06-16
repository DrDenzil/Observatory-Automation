#!/usr/bin/env bash
set -euo pipefail

# Weather Safety Watchdog
# Monitors weather and triggers emergency shutdown if conditions become unsafe
#
# Usage:
#   ./weather-watchdog.sh                    # Run once
#   ./weather-watchdog.sh --daemon           # Run continuously as daemon
#   ./weather-watchdog.sh --dry-run          # Test mode
#
# This script is designed to run as a systemd service

log() {
    echo "[$(date -Iseconds)] [WATCHDOG] $*"
}

error() {
    echo "[$(date -Iseconds)] [WATCHDOG ERROR] $*" >&2
}

usage() {
    cat <<EOF
Weather Safety Watchdog

Monitors weather conditions and triggers emergency shutdown if unsafe.

Usage: $0 [OPTIONS]

Options:
    --daemon           Run continuously as daemon
    --interval SECS    Check interval in seconds (default: 30)
    --threshold NUM    Consecutive unsafe readings before shutdown (default: 2)
    --dry-run          Preview mode - log but don't shutdown
    -h, --help        Show this help

Environment:
    WEATHER_SCRIPT     Path to weather script (default: /usr/local/share/indi/scripts/weather_status.p)
    SHUTDOWN_SCRIPT    Path to emergency shutdown script

EOF
}

DAEMON=false
INTERVAL="${INTERVAL:-30}"
THRESHOLD="${THRESHOLD:-2}"
DRY_RUN=false
WEATHER_SCRIPT="${WEATHER_SCRIPT:-/usr/local/share/indi/scripts/weather_status.p}"
SHUTDOWN_SCRIPT=""
UNSAFE_COUNT=0
LAST_SAFE_TIME=""
SHUTDOWN_COOLDOWN=300
LAST_SHUTDOWN=0

while [[ $# -gt 0 ]]; do
    case $1 in
        --daemon)
            DAEMON=true
            shift
            ;;
        --interval)
            INTERVAL="$2"
            shift 2
            ;;
        --threshold)
            THRESHOLD="$2"
            shift 2
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

find_scripts() {
    local script_dir
    script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    
    if [[ -z "$SHUTDOWN_SCRIPT" ]]; then
        for candidate in \
            "${script_dir}/emergency-shutdown.sh" \
            "${HOME}/ekos-runner/emergency-shutdown.sh" \
            "/usr/local/bin/emergency-shutdown.sh"
        do
            if [[ -f "$candidate" ]]; then
                SHUTDOWN_SCRIPT="$candidate"
                break
            fi
        done
    fi
    
    if [[ -z "$SHUTDOWN_SCRIPT" ]]; then
        error "Could not find emergency-shutdown.sh"
        exit 1
    fi
    
    if [[ ! -x "$SHUTDOWN_SCRIPT" ]]; then
        chmod +x "$SHUTDOWN_SCRIPT"
    fi
}

check_weather() {
    local json
    local open_ok
    local reasons
    
    if [[ ! -f "$WEATHER_SCRIPT" ]]; then
        log "Weather script not found: $WEATHER_SCRIPT"
        return 1
    fi
    
    json=$("$WEATHER_SCRIPT" 2>/dev/null) || return 1
    
    open_ok=$(echo "$json" | python3 -c "import sys,json; print(json.load(sys.stdin).get('roof_status',{}).get('open_ok',0))" 2>/dev/null || echo "0")
    reasons=$(echo "$json" | python3 -c "import sys,json; print(json.load(sys.stdin).get('roof_status',{}).get('reasons',''))" 2>/dev/null || echo "Unknown")
    
    if [[ "$open_ok" == "1" ]]; then
        echo "SAFE"
        echo "$reasons"
    else
        echo "UNSAFE"
        echo "$reasons"
    fi
}

trigger_shutdown() {
    local reason="$1"
    local current_time
    current_time=$(date +%s)
    
    if [[ $((current_time - LAST_SHUTDOWN)) -lt $SHUTDOWN_COOLDOWN ]]; then
        log "Shutdown cooldown active (${SHUTDOWN_COOLDOWN}s), skipping"
        return 0
    fi
    
    LAST_SHUTDOWN=$current_time
    
    log "=========================================="
    log "  WEATHER UNSAFE - TRIGGERING SHUTDOWN"
    log "  Reason: $reason"
    log "=========================================="
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY-RUN] Would call: $SHUTDOWN_SCRIPT --weather --reason '$reason'"
    else
        "$SHUTDOWN_SCRIPT" --weather --reason "$reason"
    fi
    
    UNSAFE_COUNT=0
}

run_check() {
    local status
    local reasons
    local current_time
    
    status=$(check_weather 2>&1) || status="ERROR"
    reasons=$(echo "$status" | tail -n +2 | head -1 || echo "")
    status=$(echo "$status" | head -1)
    current_time=$(date -Iseconds)
    
    case "$status" in
        SAFE)
            if [[ $UNSAFE_COUNT -gt 0 ]]; then
                log "Weather recovered: $reasons"
            fi
            UNSAFE_COUNT=0
            LAST_SAFE_TIME="$current_time"
            ;;
        UNSAFE)
            UNSAFE_COUNT=$((UNSAFE_COUNT + 1))
            log "UNSAFE ($UNSAFE_COUNT/$THRESHOLD): $reasons"
            
            if [[ $UNSAFE_COUNT -ge $THRESHOLD ]]; then
                trigger_shutdown "$reasons"
            fi
            ;;
        ERROR)
            UNSAFE_COUNT=$((UNSAFE_COUNT + 1))
            log "Weather check ERROR (treating as unsafe): $reasons"
            
            if [[ $UNSAFE_COUNT -ge $THRESHOLD ]]; then
                trigger_shutdown "Weather check failed"
            fi
            ;;
    esac
}

main() {
    find_scripts
    
    log "Weather Watchdog Starting"
    log "  Script: $WEATHER_SCRIPT"
    log "  Shutdown: $SHUTDOWN_SCRIPT"
    log "  Interval: ${INTERVAL}s"
    log "  Threshold: ${THRESHOLD} consecutive unsafe readings"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "DRY-RUN MODE - Will not trigger shutdown"
    fi
    
    if [[ "$DAEMON" == "true" ]]; then
        log "Running as daemon (Ctrl+C to stop)"
        while true; do
            run_check
            sleep "$INTERVAL"
        done
    else
        run_check
    fi
}

main
