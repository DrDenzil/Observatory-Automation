#!/usr/bin/env bash
# Continuous runner for EKOS automation
# Runs the pipeline in a loop until no more jobs remain
# Then sleeps and checks again for new jobs

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MACHINE_ID="${1:-scope03}"
CHECK_INTERVAL=300  # 5 minutes between checks when idle
MAX_LOOP_TIME=43200  # 12 hours max before restart

echo "Starting continuous EKOS runner for ${MACHINE_ID}..."
echo "Max loop time: $(($MAX_LOOP_TIME/3600)) hours"

start_time=$(date +%s)

while true; do
    # Check if we've been running too long
    current_time=$(date +%s)
    if [[ $(($current_time - $start_time)) -gt $MAX_LOOP_TIME ]]; then
        echo "[$(date -Iseconds)] Max loop time reached - restarting service"
        exit 0
    fi
    
    # Run one cycle (run.sh handles monitoring)
    echo ""
    echo "=== Starting new cycle at $(date -Iseconds) ==="
    
    cd "${SCRIPT_DIR}"
    ./run.sh "${MACHINE_ID}"
    exit_code=$?
    
    if [[ $exit_code -ne 0 ]]; then
        echo "[$(date -Iseconds)] Pipeline exited with error (code: $exit_code)"
        echo "[$(date -Iseconds)] Waiting $CHECK_INTERVAL seconds before retry..."
        sleep $CHECK_INTERVAL
        continue
    fi
    
    # Check if there are more jobs
    incoming=$(ls /var/lib/ekos-runner/jobs/${MACHINE_ID}/incoming/*.json 2>/dev/null | wc -l)
    generated=$(ls -d /var/lib/ekos-runner/jobs/${MACHINE_ID}/generated/*/ 2>/dev/null | wc -l)
    pushed=$(ls /var/lib/ekos-runner/jobs/${MACHINE_ID}/generated/*/.pushed 2>/dev/null | wc -l)
    
    if [[ $incoming -eq 0 ]] && [[ $generated -eq $pushed ]]; then
        echo "[$(date -Iseconds)] No more jobs - sleeping $CHECK_INTERVAL seconds..."
        sleep $CHECK_INTERVAL
    else
        echo "[$(date -Iseconds)] More jobs pending - starting next cycle..."
        sleep 10
    fi
done
