#!/usr/bin/env bash
# Manual run script for EKOS automation
# Runs full pipeline and monitors until all jobs complete
# Usage: Run with 'nohup ./run.sh scope03 &' to run in background

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MACHINE_ID="${1:-scope03}"
MAX_WAIT_TIME=7200
CHECK_INTERVAL=30

# Load notification helper
source "${SCRIPT_DIR}/scripts/notify.sh" 2>/dev/null || notify() { echo "Notify: $1 - $2"; }

echo "Running EKOS automation for ${MACHINE_ID}..."
echo ""

cd "${SCRIPT_DIR}"

echo "=== Step 1: Pull Jobs ==="
./pull_jobs.sh --machine-id "${MACHINE_ID}"

echo ""
echo "=== Step 2: Process Jobs ==="
./ekos_runner.py --machine-id "${MACHINE_ID}"

echo ""
echo "=== Step 3: Load Scheduler ==="
./load_scheduler.sh --machine-id "${MACHINE_ID}"

echo ""
echo "=== Step 4: Monitoring for captures ==="
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
    
    # Status 2 = running, 0 = idle, 1 = ready/loaded
    if [[ "$status" == "2" ]]; then
        echo -ne "\r[$(date -Iseconds)] Running: $current_job (elapsed: ${elapsed}s)   \r"
    elif [[ "$status" == "0" || "$status" == "1" ]] && [[ "$pending_captures" -gt 0 ]]; then
        # Job finished -> capture new images -> push -> load next
        echo "[$(date -Iseconds)] Job completed (status=$status), pushing $pending_captures captures..."
        ./push_jobs.sh --machine-id "${MACHINE_ID}" 2>/dev/null || true
        
        # Check for more pending jobs
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
            echo "[$(date -Iseconds)] Loading next job ($pending_jobs remaining)..."
            ./load_scheduler.sh --machine-id "${MACHINE_ID}"
            sleep 3
        else
            echo "[$(date -Iseconds)] No more pending jobs"
            break
        fi
    fi
    
    sleep $CHECK_INTERVAL
done

echo ""
echo "Done! $completed_jobs job(s) completed and pushed."