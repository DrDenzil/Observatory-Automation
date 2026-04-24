#!/usr/bin/env bash
set -euo pipefail

# Push FITS captures to the central server via fitsin/
# FITS files are processed by import.php on the server

log() {
    echo "[$(date -Iseconds)] $*"
}

inject_fits_headers() {
    local fits_file="$1"
    local queue_ref="$2"
    local project="$3"
    local submitted_by="$4"
    local target="$5"
    local filter="$6"
    local exptime="$7"
    
    # Generate GUID from queue_ref (ekos_1234_xxx -> 1234)
    local guid=$(echo "$queue_ref" | sed -n 's/ekos_\([0-9]*\)_.*/\1/p')
    
    # Use Python with astropy to inject headers
    python3 << PYEOF
from astropy.io import fits
import sys

try:
    hdu = fits.open('$fits_file', mode='update')
    hdr = hdu[0].header
    
    # Add required headers (don't overwrite if exists)
    if 'GUID' not in hdr:
        hdr['GUID'] = ('$guid', 'RTML Job ID')
    
    if 'OBSERVER' not in hdr and '$submitted_by':
        hdr['OBSERVER'] = (str('$submitted_by'), 'Observer user ID')
    
    if 'PRJNAME' not in hdr and '$project':
        hdr['PRJNAME'] = ('$project', 'Project name')
    
    if 'PLNNAME' not in hdr and '$target':
        hdr['PLNNAME'] = ('$target', 'Plan/target name')
    
    if 'INSTRUME' not in hdr:
        hdr['INSTRUME'] = ('CKT Telescope', 'Telescope name')
    
    if 'TELESCOP' not in hdr:
        hdr['TELESCOP'] = ('CKT', 'Telescope ID')
    
    if 'PRJID' not in hdr and '$guid':
        hdr['PRJID'] = (int('$guid') if '$guid'.isdigit() else 0, 'Project ID')
    
    hdu.close()
    print('Headers injected OK')
except Exception as e:
    print(f'Header injection failed: {e}', file=sys.stderr)
    sys.exit(1)
PYEOF
    
    return $?
}

usage() {
    cat <<EOF
Usage: $0 [OPTIONS]

Options:
    --machine-id ID      Machine ID (default: scope06)
    --queue-root PATH    Local queue root (default: /var/lib/ekos-runner/jobs)
    --dry-run            Preview what would be pushed
    --local PATH         Local mode (for testing)
    -h, --help           Show this help message

Environment variables:
    MACHINE_ID           Machine ID (default: scope06)
    PUSH_USER            SSH user (default: robotic)
    PUSH_HOST            SSH host (default: star-server)
    FITS_IN              Remote FITS import queue (default: /www/bayfordbury/automation/control/fitsin)
    SSH_PASSWORD         SSH password (alternative to --password)

Note:
    FITS files are uploaded to fitsin/ as {dbid}.fit for processing by import.php
    The import.php script extracts metadata, registers in database, and organizes files

Examples:
    $0 --machine-id scope01                    # Push FITS to server
    $0 --machine-id scope01 --dry-run          # Preview
    $0 --machine-id scope01 --local /tmp/jobs  # Local mode
EOF
}

MACHINE_ID="${MACHINE_ID:-scope06}"
PUSH_USER="${PUSH_USER:-robotic}"
PUSH_HOST="${PUSH_HOST:-star-server}"
FITS_IN="${FITS_IN:-/www/bayfordbury/automation/control/fitsin}"
LOCAL_BASE="${LOCAL_BASE:-/var/lib/ekos-runner/jobs}"
SSH_PASSWORD="${SSH_PASSWORD:-}"
SSH_OPTS="${SSH_OPTS:--o BatchMode=yes}"

DRY_RUN=false
LOCAL_MODE=false
LOCAL_PUSH_PATH=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --machine-id)
            MACHINE_ID="$2"
            shift 2
            ;;
        --queue-root)
            LOCAL_BASE="$2"
            shift 2
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --local)
            LOCAL_MODE=true
            LOCAL_PUSH_PATH="$2"
            shift 2
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

if [[ "${LOCAL_MODE}" == "true" ]]; then
    GENERATED_PATH="${LOCAL_PUSH_PATH}/${MACHINE_ID}/generated"
else
    GENERATED_PATH="${LOCAL_BASE}/${MACHINE_ID}/generated"
fi

get_next_dbid() {
    local next_dbid=$(ssh ${SSH_OPTS} "${PUSH_USER}@${PUSH_HOST}" "php -r 'require \"/www/bayfordbury/private/db.php\"; \$link = mysqli_connect(\"localhost\", \$dbUser, \$dbPassword, \$dbDb); \$q = mysqli_query(\$link, \"SELECT MAX(dbid) as max_id FROM images\"); \$r = mysqli_fetch_assoc(\$q); echo \$r[\"max_id\"] + 1; mysqli_close(\$link);'" 2>/dev/null)
    echo "${next_dbid:-112523}"
}

if [[ ! -d "${GENERATED_PATH}" ]]; then
    log "No captures directory found"
else
    CAPTURE_DIRS=("${GENERATED_PATH}"/*/captures)
    if [[ -d "${CAPTURE_DIRS[0]:-}" ]]; then
        
        if [[ "${LOCAL_MODE}" == "false" ]]; then
            log "Getting next DBID from server..."
            CURRENT_DBID=$(get_next_dbid)
            log "Starting DBID: ${CURRENT_DBID}"
        else
            CURRENT_DBID=999999
        fi
        
        for captures_dir in "${GENERATED_PATH}"/*/captures; do
            queue_ref=$(basename "$(dirname "${captures_dir}")")
            
            FITS_FILES=()
            while IFS= read -r -d '' file; do
                FITS_FILES+=("$file")
            done < <(find "${captures_dir}" -name "*.fit*" -print0 2>/dev/null)
            
            FITS_COUNT=${#FITS_FILES[@]}
            if [[ "${FITS_COUNT}" -gt 0 ]]; then
                log "Processing ${FITS_COUNT} FITS files for ${queue_ref}..."
                
                # Get job info from manifest
                local manifest="${captures_dir}/../manifest.json"
                local job_project="Unknown"
                local job_submitted_by="0"
                if [[ -f "$manifest" ]]; then
                    job_project=$(python3 -c "import json; print(json.load(open('$manifest')).get('project', 'Unknown'))" 2>/dev/null || echo "Unknown")
                    job_submitted_by=$(python3 -c "import json; print(json.load(open('$manifest')).get('submitted_by', '0'))" 2>/dev/null || echo "0")
                fi
                
                # Get target info from target files
                local job_target="Unknown"
                local target_json="${captures_dir}/../targets/"*.json 2>/dev/null
                if [[ -f "$target_json" ]]; then
                    job_target=$(python3 -c "import json; print(json.load(open('$target_json')).get('name', 'Unknown'))" 2>/dev/null || echo "Unknown")
                fi
                
                if [[ "${DRY_RUN}" == "true" ]]; then
                    log "[DRY-RUN] Would upload ${FITS_COUNT} FITS files to fitsin/"
                    for fits_file in "${FITS_FILES[@]}"; do
                        filename=$(basename "$fits_file")
                        log "  ${CURRENT_DBID}.fit <- ${filename}"
                        ((CURRENT_DBID++))
                    done
                else
                    for fits_file in "${FITS_FILES[@]}"; do
                        filename=$(basename "$fits_file")
                        
                        # Extract filter and exposure from filename if possible
                        local fits_filter=$(echo "$filename" | grep -oE '_[A-Z]+_' | tr -d '_' || echo "R")
                        local fits_exp=$(echo "$filename" | grep -oE '[0-9]+\.[0-9]+s' | tr -d 's' || echo "10")
                        
                        # Inject FITS headers
                        inject_fits_headers "$fits_file" "$queue_ref" "$job_project" "$job_submitted_by" "$job_target" "$fits_filter" "$fits_exp" || true
                        
                        if [[ "${LOCAL_MODE}" == "true" ]]; then
                            mkdir -p "${LOCAL_PUSH_PATH}/fitsin"
                            cp "$fits_file" "${LOCAL_PUSH_PATH}/fitsin/${CURRENT_DBID}.fit"
                        else
                            scp ${SSH_OPTS} -i ~/.ssh/id_rsa_star "$fits_file" "${PUSH_USER}@${PUSH_HOST}:${FITS_IN}/${CURRENT_DBID}.fit" 2>/dev/null || \
                            ssh ${SSH_OPTS} "${PUSH_USER}@${PUSH_HOST}" "cat > '${FITS_IN}/${CURRENT_DBID}.fit'" < "$fits_file"
                        fi
                        
                        log "Uploaded: ${filename} -> ${CURRENT_DBID}.fit"
                        ((CURRENT_DBID++))
                    done
                fi
            else
                log "No FITS files in ${captures_dir}"
            fi
        done
        
        if [[ "${LOCAL_MODE}" == "false" ]]; then
            log "FITS files uploaded to ${FITS_IN}/"
            
            log "Triggering import.php..."
            ssh ${SSH_OPTS} "${PUSH_USER}@${PUSH_HOST}" \
                "cd /www/bayfordbury/automation/control/fitsin && php import.php" 2>/dev/null || \
                log "Warning: import.php trigger failed"
        fi
    else
        log "No capture directories found"
    fi
fi

log "Push complete"

# Load scheduler into KStars (local only)
if [[ "${DRY_RUN}" == "false" && "${LOCAL_MODE}" == "false" ]]; then
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    if [[ -x "${SCRIPT_DIR}/load_scheduler.sh" ]]; then
        log "Loading scheduler into KStars..."
        "${SCRIPT_DIR}/load_scheduler.sh" --machine-id "${MACHINE_ID}" --queue-root "${LOCAL_BASE}" || true
    else
        log "Note: load_scheduler.sh not found or not executable"
    fi
fi

# Update rtml status in database (only when pushing to real server)
if [[ "${DRY_RUN}" == "false" && "${LOCAL_MODE}" == "false" ]]; then
    log "Updating job status to completed..."
    if [[ -n "${SSH_PASSWORD}" ]]; then
        sshpass -p "${SSH_PASSWORD}" ssh ${SSH_OPTS} "${PUSH_USER}@${PUSH_HOST}" \
            "php /www/bayfordbury/automation/update_rtml_status.php --set-status completed --machine-id ${MACHINE_ID}" || true
    else
        ssh ${SSH_OPTS} "${PUSH_USER}@${PUSH_HOST}" \
            "php /www/bayfordbury/automation/update_rtml_status.php --set-status completed --machine-id ${MACHINE_ID}" || true
    fi
fi
