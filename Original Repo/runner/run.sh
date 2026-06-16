#!/usr/bin/env bash
# Manual run script for EKOS automation
# Runs full pipeline and monitors until all jobs complete
# Usage: Run with 'nohup ./run.sh scope03 &' to run in background

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MACHINE_ID="${1:-scope03}"
MAX_WAIT_TIME=7200
CHECK_INTERVAL=30
INDI_PORT="${INDI_PORT:-7624}"

if [[ -z "${INDI_DRIVERS+x}" ]]; then
    if [[ "${MACHINE_ID}" == "scope03" ]]; then
        INDI_DRIVERS="indi_simulator_telescope indi_simulator_ccd indi_weather_safety_proxy"
    else
        INDI_DRIVERS=""
    fi
fi

if [[ -z "${EKOS_PROFILE+x}" ]]; then
    if [[ "${MACHINE_ID}" == "scope03" ]]; then
        EKOS_PROFILE="Simulators"
    else
        EKOS_PROFILE="${MACHINE_ID}"
    fi
fi

# Load notification helper
source "${SCRIPT_DIR}/scripts/notify.sh" 2>/dev/null || notify() { echo "Notify: $1 - $2"; }

echo "Running EKOS automation for ${MACHINE_ID}..."
echo "EKOS profile: ${EKOS_PROFILE}"
if [[ -n "${INDI_DRIVERS}" ]]; then
    echo "INDI drivers: ${INDI_DRIVERS}"
else
    echo "INDI drivers: external/manual (INDI_DRIVERS not set)"
fi
echo ""

cd "${SCRIPT_DIR}"

# =============================================================================
# Crash Recovery Functions
# =============================================================================

# Restart INDI server if not running
ensure_indi_running() {
    if pgrep -x indiserver > /dev/null 2>&1; then
        return 0
    fi
    if [[ -z "${INDI_DRIVERS}" ]]; then
        echo "[$(date -Iseconds)] INDI server not running and INDI_DRIVERS is not set"
        echo "[$(date -Iseconds)] Set INDI_DRIVERS for ${MACHINE_ID} before unattended production use"
        return 1
    fi
    echo "[$(date -Iseconds)] INDI server not running, restarting..."
    pkill -f indiserver 2>/dev/null || true
    sleep 1
    nohup /usr/bin/indiserver -v -p "${INDI_PORT}" -m 1024 -r 0 -f /tmp/indififo \
        ${INDI_DRIVERS} > /tmp/indiserver.log 2>&1 &
    sleep 3
    if pgrep -x indiserver > /dev/null 2>&1; then
        echo "[$(date -Iseconds)] INDI server started"
        return 0
    fi
    echo "[$(date -Iseconds)] Failed to start INDI server"
    return 1
}

# Restart KStars if D-Bus is unavailable (waits up to 30s for D-Bus registration)
ensure_kstars_running() {
    if dbus-send --session --dest=org.kde.kstars --print-reply \
        /KStars org.freedesktop.DBus.Introspectable.Introspect > /dev/null 2>&1; then
        return 0
    fi
    echo "[$(date -Iseconds)] KStars not responding, restarting..."
    pkill -f kstars 2>/dev/null || true
    sleep 2
    kstars &>/dev/null &
    local waited=0
    while [[ $waited -lt 30 ]]; do
        sleep 1
        waited=$((waited + 1))
        if dbus-send --session --dest=org.kde.kstars --print-reply \
            /KStars org.freedesktop.DBus.Introspectable.Introspect > /dev/null 2>&1; then
            echo "[$(date -Iseconds)] KStars started"
            return 0
        fi
    done
    echo "[$(date -Iseconds)] Failed to start KStars"
    return 1
}

# Wait for EKOS D-Bus, set profile, and connect to INDI
ensure_ekos_connected() {
    local waited=0
    # Wait up to 30s for /KStars/Ekos D-Bus path to appear
    while [[ $waited -lt 30 ]]; do
        if dbus-send --session --dest=org.kde.kstars --print-reply \
            /KStars/Ekos org.freedesktop.DBus.Introspectable.Introspect > /dev/null 2>&1; then
            break
        fi
        sleep 1
        waited=$((waited + 1))
    done
    if [[ $waited -ge 30 ]]; then
        echo "[$(date -Iseconds)] EKOS D-Bus path not available"
        return 1
    fi

    # Check current INDI connection status (0=idle, 1=connecting, 2=connected)
    local indi_status
    indi_status=$(dbus-send --session --dest=org.kde.kstars --print-reply \
        /KStars/Ekos org.freedesktop.DBus.Properties.Get \
        string:"org.kde.kstars.Ekos" string:"indiStatus" 2>/dev/null | \
        grep -o 'int32 [0-9]' | awk '{print $2}')

    if [[ "$indi_status" == "2" ]]; then
        return 0
    fi

    echo "[$(date -Iseconds)] Connecting EKOS to profile '${EKOS_PROFILE}'..."
    dbus-send --session --dest=org.kde.kstars --print-reply \
        /KStars/Ekos org.kde.kstars.Ekos.setProfile \
        string:"${EKOS_PROFILE}" > /dev/null 2>&1 || true
    sleep 2
    dbus-send --session --dest=org.kde.kstars --print-reply \
        /KStars/Ekos org.kde.kstars.Ekos.connectDevices > /dev/null 2>&1 || true

    # Wait for connection
    waited=0
    while [[ $waited -lt 30 ]]; do
        sleep 1
        waited=$((waited + 1))
        indi_status=$(dbus-send --session --dest=org.kde.kstars --print-reply \
            /KStars/Ekos org.freedesktop.DBus.Properties.Get \
            string:"org.kde.kstars.Ekos" string:"indiStatus" 2>/dev/null | \
            grep -o 'int32 [0-9]' | awk '{print $2}')
        if [[ "$indi_status" == "2" ]]; then
            echo "[$(date -Iseconds)] EKOS connected to INDI"
            return 0
        fi
    done
    echo "[$(date -Iseconds)] Failed to connect EKOS to INDI (status: ${indi_status:-unknown})"
    return 1
}

echo "=== Step 1: Pull Jobs ==="
./pull_jobs.sh --machine-id "${MACHINE_ID}"

echo ""
echo "=== Step 2: Process Jobs ==="
./ekos_runner.py --machine-id "${MACHINE_ID}"

echo ""
echo "=== Step 3: Connect EKOS Profile ==="
if ! ensure_kstars_running; then
    notify "Pipeline Error" "Machine ${MACHINE_ID}: KStars failed to start"
    exit 1
fi
if ! ensure_ekos_connected; then
    notify "Pipeline Error" "Machine ${MACHINE_ID}: EKOS failed to connect profile ${EKOS_PROFILE}"
    exit 1
fi

echo ""
echo "=== Step 4: Load Scheduler ==="
./load_scheduler.sh --machine-id "${MACHINE_ID}"

echo ""
echo "=== Step 5: Monitoring for captures ==="
echo "(Watching scheduler - will load next job when current completes)"
echo "Press Ctrl+C to stop monitoring"
echo ""

start_time=$(date +%s)
completed_jobs=0

while true; do
    elapsed=$(($(date +%s) - start_time))
    
    if [[ $elapsed -gt $MAX_WAIT_TIME ]]; then
        echo "[$(date -Iseconds)] Max wait time reached - stopping"
        notify "Pipeline Error" "Machine ${MACHINE_ID}: Max wait time reached after ${elapsed}s"
        break
    fi

    # Check scheduler status
    status=$(dbus-send --session --dest=org.kde.kstars --print-reply /KStars/Ekos/Scheduler \
        org.freedesktop.DBus.Properties.Get string:"org.kde.kstars.Ekos.Scheduler" string:"status" 2>/dev/null | \
        grep -o 'int32 [0-9]' | awk '{print $2}')
    
    # Get current job name
    current_job=$(dbus-send --session --dest=org.kde.kstars --print-reply /KStars/Ekos/Scheduler \
        org.freedesktop.DBus.Properties.Get string:"org.kde.kstars.Ekos.Scheduler" string:"currentJobName" 2>/dev/null | \
        grep -o 'string "[^"]*"' | sed 's/string "//;s/"//')
    
    # Check scheduler status and if captures exist
    captures_total=$(ls "/var/lib/ekos-runner/jobs/${MACHINE_ID}/generated/"*"/captures/"*.fits 2>/dev/null | wc -l)
    pushed_total=$(ls "/var/lib/ekos-runner/jobs/${MACHINE_ID}/generated/"*"/.pushed" 2>/dev/null | wc -l)
    pending_captures=$((captures_total - pushed_total))
    
    # Detect crash: KStars D-Bus unavailable (empty status)
    if [[ -z "$status" ]]; then
        echo "[$(date -Iseconds)] KStars D-Bus unavailable - attempting restart..."
        notify "Pipeline Recovering" "Machine ${MACHINE_ID}: KStars/INDI down, restarting"
        ensure_indi_running
        ensure_kstars_running
        ensure_ekos_connected
        sleep 5
        continue
    fi

    # INDI health check (indiserver handles INDI; KStars can stay up alone)
    if ! pgrep -x indiserver > /dev/null 2>&1; then
        echo "[$(date -Iseconds)] INDI server process not found - restarting..."
        notify "Pipeline Recovering" "Machine ${MACHINE_ID}: INDI down, restarting"
        ensure_indi_running
        ensure_ekos_connected
    fi

    # Status 2 = running, 0 = idle, 1 = ready/loaded
    if [[ "$status" == "2" ]]; then
        echo -ne "\r[$(date -Iseconds)] Running: $current_job (elapsed: ${elapsed}s)   \r"
    elif [[ "$status" == "0" || "$status" == "1" ]] && [[ "$pending_captures" -gt 0 ]]; then
        # Job finished -> capture new images -> push -> load next
        echo "[$(date -Iseconds)] Job completed (status=$status), pushing $pending_captures captures..."
        ./push_jobs.sh --machine-id "${MACHINE_ID}" 2>/dev/null || true
        completed_jobs=$((completed_jobs + 1))
        
        # Check for more pending jobs (not yet captured or not yet pushed)
        pending_jobs=0
        pending_push=0
        for dir in /var/lib/ekos-runner/jobs/${MACHINE_ID}/generated/*/; do
            if [[ -f "$dir/.pushed" ]]; then
                continue
            fi
            captures=$(ls "$dir/captures/"*.fits 2>/dev/null | wc -l)
            if [[ "$captures" -eq 0 ]]; then
                pending_jobs=$((pending_jobs + 1))
            else
                pending_push=$((pending_push + 1))
            fi
        done
        
        if [[ $pending_jobs -gt 0 ]]; then
            echo "[$(date -Iseconds)] Loading next job ($pending_jobs remaining)..."
            ./load_scheduler.sh --machine-id "${MACHINE_ID}"
            sleep 3
        elif [[ $pending_push -gt 0 ]]; then
            echo "[$(date -Iseconds)] $pending_push job(s) waiting to be pushed, continuing monitoring..."
            sleep $CHECK_INTERVAL
        else
            echo "[$(date -Iseconds)] No more pending jobs"
            break
        fi
    elif [[ "$status" == "0" || "$status" == "1" ]] && [[ "$pending_captures" -eq 0 ]]; then
        # Scheduler idle + no captures: (re)load next pending job after crash recovery
        pending_jobs=0
        for dir in /var/lib/ekos-runner/jobs/${MACHINE_ID}/generated/*/; do
            if [[ -f "$dir/.pushed" ]]; then
                continue
            fi
            captures=$(ls "$dir/captures/"*.fits 2>/dev/null | wc -l)
            if [[ "$captures" -eq 0 ]]; then
                pending_jobs=$((pending_jobs + 1))
            fi
        done
        if [[ $pending_jobs -gt 0 ]]; then
            echo "[$(date -Iseconds)] Scheduler idle with $pending_jobs pending job(s) - loading next..."
            ./load_scheduler.sh --machine-id "${MACHINE_ID}"
            sleep 3
        fi
    fi
    
    sleep $CHECK_INTERVAL
done

echo ""
echo "Done! $completed_jobs job(s) completed and pushed."
