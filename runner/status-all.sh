#!/usr/bin/env bash
# Check status of ALL EKOS Runner scopes
# Run from any machine or telescope

set -euo pipefail

QUEUE_ROOT="/var/lib/ekos-runner/jobs"
STAR_HOST="star-server"
STAR_USER="ds"

echo ""
echo "=============================================="
echo "  EKOS Runner - All Scopes Status"
echo "=============================================="
echo ""

total_jobs=0
total_incoming=0
total_completed=0
total_failed=0

for i in 01 02 03 04 05 06 07 08 09; do
    scope="scope${i}"
    incoming=$(find "${QUEUE_ROOT}/${scope}/incoming" -maxdepth 1 -name "*.json" 2>/dev/null | wc -l)
    claimed=$(find "${QUEUE_ROOT}/${scope}/claimed" -maxdepth 1 -name "*.json" 2>/dev/null | wc -l)
    completed=$(find "${QUEUE_ROOT}/${scope}/completed" -maxdepth 1 -name "*.json" 2>/dev/null | wc -l)
    failed=$(find "${QUEUE_ROOT}/${scope}/failed" -maxdepth 1 -name "*.json" 2>/dev/null | wc -l)
    
    # Count FITS files
    fits_count=$(find "${QUEUE_ROOT}/${scope}/generated" -name "*.fit*" 2>/dev/null | wc -l)
    
    printf "%-8s | Incoming: %2d | Claimed: %2d | Completed: %2d | Failed: %2d | FITS: %3d\n" \
        "$scope" "$incoming" "$claimed" "$completed" "$failed" "$fits_count"
    
    total_incoming=$((total_incoming + incoming))
    total_completed=$((total_completed + completed))
    total_failed=$((total_failed + failed))
    total_jobs=$((total_jobs + incoming + claimed + completed + failed))
done

echo ""
echo "----------------------------------------------"
printf "  TOTALS | Incoming: %2d | Completed: %3d | Failed: %3d\n" \
    "$total_incoming" "$total_completed" "$total_failed"
echo "----------------------------------------------"
echo ""

# Check last run times
echo "Last automation runs:"
for i in 01 02 03 04 05 06 07 08 09; do
    scope="scope${i}"
    log_file="/var/log/ekos-runner-${scope}.log"
    if [[ -f "$log_file" ]]; then
        last_run=$(tail -1 "$log_file" 2>/dev/null | cut -d']' -f2- | xargs)
        echo "  ${scope}: ${last_run:-Unknown}"
    fi
done

echo ""
echo "Server queue check:"
server_incoming=$(ssh -o BatchMode=yes -o ConnectTimeout=5 "${STAR_USER}@${STAR_HOST}" \
    "find /www/bayfordbury/automation/jobs/outgoing -type f -name '*.json' 2>/dev/null | wc -l" 2>/dev/null || echo "N/A")
echo "  Pending jobs on server: ${server_incoming}"
echo ""
