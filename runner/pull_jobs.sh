#!/usr/bin/env bash
set -euo pipefail

# Prototype pull script for an Ubuntu 24.04 EKOS machine.
# Pulls jobs from the central server's per-machine outgoing directory
# into the local runner incoming directory using rsync over SSH.
# Supports local mode for testing without SSH connectivity.

log() {
    echo "[$(date -Iseconds)] $*"
}

usage() {
    cat <<EOF
Usage: $0 [OPTIONS]

Options:
    --machine-id ID      Machine ID (default: scope06)
    --local PATH         Use local path instead of SSH (for testing)
    --password PASS      SSH password (for password-based auth)
    --dry-run            Preview what would be pulled without copying
    -h, --help           Show this help message

Environment variables:
    MACHINE_ID           Machine ID (default: scope06)
    REMOTE_USER          SSH user (default: denis)
    REMOTE_HOST          SSH host (default: observatory-server)
    REMOTE_BASE          Remote base path (default: /var/www/html/jobs)
    LOCAL_BASE           Local base path (default: /var/lib/ekos-runner/jobs)
    SSH_PASSWORD         SSH password (alternative to --password)

Examples:
    $0 --machine-id scope01                          # Pull from remote server
    $0 --machine-id scope01 --local /tmp/jobs        # Pull from local directory
    $0 --machine-id scope01 --password secret        # Pull with password auth
    $0 --machine-id scope01 --dry-run                # Preview without copying
EOF
}

MACHINE_ID="${MACHINE_ID:-scope06}"
REMOTE_USER="${REMOTE_USER:-robotic}"
REMOTE_HOST="${REMOTE_HOST:-star-server}"
REMOTE_BASE="${REMOTE_BASE:-/www/bayfordbury/automation/jobs}"
LOCAL_BASE="${LOCAL_BASE:-/var/lib/ekos-runner/jobs}"
SSH_OPTS="${SSH_OPTS:--o BatchMode=yes}"
SSH_PASSWORD="${SSH_PASSWORD:-}"

DRY_RUN=false
LOCAL_MODE=false
LOCAL_PATH=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --machine-id)
            MACHINE_ID="$2"
            shift 2
            ;;
        --local)
            LOCAL_MODE=true
            LOCAL_PATH="$2"
            shift 2
            ;;
        --password)
            SSH_PASSWORD="$2"
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

INCOMING_PATH="${LOCAL_BASE}/${MACHINE_ID}/incoming/"

# Ensure parent directories exist (use sudo if needed)
if [[ ! -d "${LOCAL_BASE}/${MACHINE_ID}" ]]; then
    mkdir -p "${LOCAL_BASE}/${MACHINE_ID}" 2>/dev/null || \
        sudo mkdir -p "${LOCAL_BASE}/${MACHINE_ID}" 2>/dev/null || \
        mkdir -p "${LOCAL_BASE}/${MACHINE_ID}"
fi

if [[ "${LOCAL_MODE}" == "true" ]]; then
    if [[ -z "${LOCAL_PATH}" ]]; then
        echo "Error: --local requires a path argument" >&2
        exit 1
    fi
    log "Pulling jobs from local path: ${LOCAL_PATH}"
    log "Machine ID: ${MACHINE_ID}"
    REMOTE_PATH="${LOCAL_PATH}/outgoing/${MACHINE_ID}/"
else
    log "Pulling jobs from remote server: ${REMOTE_USER}@${REMOTE_HOST}"
    log "Machine ID: ${MACHINE_ID}"
    REMOTE_PATH="${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_BASE}/outgoing/${MACHINE_ID}/"
fi

if [[ "${LOCAL_MODE}" == "true" && ! -d "${REMOTE_PATH}" ]]; then
    log "Source path does not exist: ${REMOTE_PATH}"
    exit 0
fi

mkdir -p "${INCOMING_PATH}"

RSYNC_CMD="rsync -av --ignore-existing"
if [[ "${LOCAL_MODE}" != "true" ]]; then
    if [[ -n "${SSH_PASSWORD}" ]]; then
        RSYNC_CMD="sshpass -p '${SSH_PASSWORD}' rsync -av --ignore-existing -e 'ssh ${SSH_OPTS}'"
    else
        RSYNC_CMD="rsync -av --ignore-existing -e 'ssh ${SSH_OPTS}'"
    fi
fi

if [[ "${DRY_RUN}" == "true" ]]; then
    log "[DRY-RUN] Would copy files from ${REMOTE_PATH} to ${INCOMING_PATH}"
    if [[ "${LOCAL_MODE}" == "true" ]]; then
        rsync -av --ignore-existing --dry-run "${REMOTE_PATH}" "${INCOMING_PATH}"
    else
        eval "${RSYNC_CMD} --dry-run ${REMOTE_PATH} ${INCOMING_PATH}"
        log "[DRY-RUN] Would move files to sent/ on server"
    fi
else
    log "Copying jobs to ${INCOMING_PATH}"
    if [[ "${LOCAL_MODE}" == "true" ]]; then
        rsync -av --ignore-existing "${REMOTE_PATH}" "${INCOMING_PATH}"
        log "Pull complete"
    else
        eval "${RSYNC_CMD} ${REMOTE_PATH} ${INCOMING_PATH}"
        
        if [[ $? -eq 0 ]]; then
            log "Moving files to sent/ on server"
            if [[ -n "${SSH_PASSWORD}" ]]; then
                sshpass -p "${SSH_PASSWORD}" ssh ${SSH_OPTS} "${REMOTE_USER}@${REMOTE_HOST}" \
                    "mkdir -p ${REMOTE_BASE}/sent/${MACHINE_ID} && mv ${REMOTE_BASE}/outgoing/${MACHINE_ID}/*.json ${REMOTE_BASE}/sent/${MACHINE_ID}/ 2>/dev/null || true"
            else
                ssh ${SSH_OPTS} "${REMOTE_USER}@${REMOTE_HOST}" \
                    "mkdir -p ${REMOTE_BASE}/sent/${MACHINE_ID} && mv ${REMOTE_BASE}/outgoing/${MACHINE_ID}/*.json ${REMOTE_BASE}/sent/${MACHINE_ID}/ 2>/dev/null || true"
            fi
            log "Pull complete"
        fi
    fi
fi
