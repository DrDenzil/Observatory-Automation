#!/usr/bin/env bash
# Deploy EKOS Runner to ALL telescope machines
# Run this from a machine with SSH access to all telescopes

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PKG_FILE="/tmp/ekos-deploy.tar.gz"
SCOPES=("scope01" "scope02" "scope03" "scope04" "scope05" "scope06" "scope07" "scope08" "scope09")
SSH_USER="ds"
PARALLEL=false
INSTALL_CRON=false

usage() {
    cat <<EOF
Deploy EKOS Runner to All Telescopes

Usage: $0 [OPTIONS]

Options:
    --scopes "scope01 scope02 ..."   Specific scopes to deploy (default: all 01-09)
    --parallel                        Deploy to multiple machines in parallel
    --install-cron                   Install cron jobs on all machines
    --dry-run                        Show what would be done without executing
    -h, --help                       Show this help

Examples:
    $0                               Deploy to all 9 telescopes
    $0 --scopes "scope01 scope03"    Deploy to specific scopes
    $0 --parallel --install-cron      Parallel deploy with cron
EOF
}

while [[ $# -gt 0 ]]; do
    case $1 in
        --scopes)
            IFS=' ' read -ra SCOPES <<< "$2"
            shift 2
            ;;
        --parallel)
            PARALLEL=true
            shift
            ;;
        --install-cron)
            INSTALL_CRON=true
            shift
            ;;
        --dry-run)
            DRY_RUN="echo"
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

DRY_RUN="${DRY_RUN:-}"

log() {
    echo "[$(date -Iseconds)] $*"
}

deploy_to_scope() {
    local scope="$1"
    local ip="$2"
    local cron_flag="$3"
    
    log "Deploying to ${scope} (${ip})..."
    
    # Create temp package with cron flag if needed
    if [[ "$INSTALL_CRON" == "true" ]]; then
        TEMP_DIR=$(mktemp -d)
        tar -xzf "${PKG_FILE}" -C "${TEMP_DIR}"
        
        # Add cron flag marker
        echo "INSTALL_CRON=true" > "${TEMP_DIR}/.deploy_flags"
        
        # Repackage
        cd "${TEMP_DIR}"
        tar -czf "${PKG_FILE}" *
        cd - > /dev/null
    fi
    
    # Transfer package
    ${DRY_RUN} scp -o ConnectTimeout=10 "${PKG_FILE}" "${SSH_USER}@${ip}:~/ekos-deploy.tar.gz" 2>/dev/null || {
        log "  FAILED: Could not copy to ${ip}"
        return 1
    }
    
    # Run deploy script
    ${DRY_RUN} ssh -o ConnectTimeout=10 "${SSH_USER}@${ip}" bash -c "'
        cd ~
        tar -xzf ekos-deploy.tar.gz 2>/dev/null || true
        chmod +x deploy.sh 2>/dev/null || true
        
        CRON_FLAG=""
        [[ -f .deploy_flags ]] && source .deploy_flags
        [[ "${INSTALL_CRON:-false}" == "true" ]] && CRON_FLAG="--install-cron"
        
        ./deploy.sh --machine-id '"${scope}"' ${CRON_FLAG} 2>&1 | head -50
    '" || {
        log "  FAILED: Deploy script error on ${ip}"
        return 1
    }
    
    log "  Done: ${scope}"
    return 0
}

# Telescope IP addresses (update these for your network)
get_scope_ip() {
    local scope="$1"
    case "$scope" in
        scope01) echo "192.168.1.101" ;;
        scope02) echo "192.168.1.102" ;;
        scope03) echo "192.168.1.103" ;;
        scope04) echo "192.168.1.104" ;;
        scope05) echo "192.168.1.105" ;;
        scope06) echo "192.168.1.106" ;;
        scope07) echo "192.168.1.107" ;;
        scope08) echo "192.168.1.108" ;;
        scope09) echo "192.168.1.109" ;;
        *) echo "" ;;
    esac
}

main() {
    echo ""
    echo "=============================================="
    echo "  EKOS Runner - Batch Deploy"
    echo "  Scopes: ${SCOPES[*]}"
    echo "=============================================="
    echo ""
    
    # Create package if needed
    if [[ ! -f "${PKG_FILE}" ]]; then
        log "Creating deployment package..."
        "${SCRIPT_DIR}/package-deploy.sh"
    fi
    
    log "Package: ${PKG_FILE}"
    log "Starting deployment..."
    echo ""
    
    SUCCESS=0
    FAILED=0
    
    if [[ "$PARALLEL" == "true" ]]; then
        # Parallel deployment
        for scope in "${SCOPES[@]}"; do
            ip=$(get_scope_ip "$scope")
            if [[ -n "$ip" ]]; then
                deploy_to_scope "$scope" "$ip" "$INSTALL_CRON" &
            fi
        done
        wait
    else
        # Sequential deployment
        for scope in "${SCOPES[@]}"; do
            ip=$(get_scope_ip "$scope")
            if [[ -n "$ip" ]]; then
                deploy_to_scope "$scope" "$ip" "$INSTALL_CRON"
                [[ $? -eq 0 ]] && ((SUCCESS++)) || ((FAILED++))
            fi
        done
    fi
    
    echo ""
    echo "=============================================="
    echo "  Deployment Complete"
    echo "  Success: ${SUCCESS}"
    echo "  Failed:  ${FAILED}"
    echo "=============================================="
}

main
