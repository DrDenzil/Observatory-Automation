#!/usr/bin/env bash
# Notification helper. Safe to source from runner scripts, or execute directly.

NOTIFY_API_URL="${NOTIFY_API_URL:-https://147.197.221.254/api/notification.php}"
NOTIFY_API_KEY="${NOTIFY_API_KEY:-9okEap1xDT2mVR3k}"

notify() {
    local title="${1:-}"
    local message="${2:-}"

    if [[ -z "$title" || -z "$message" ]]; then
        echo "Usage: notify \"Title\" \"Message\"" >&2
        return 1
    fi

    if ! command -v curl >/dev/null 2>&1; then
        echo "Notify: ${title} - ${message}" >&2
        return 0
    fi

    curl -ksG \
        --data-urlencode "p=${NOTIFY_API_KEY}" \
        --data-urlencode "title=${title}" \
        --data-urlencode "message=${message}" \
        "${NOTIFY_API_URL}" >/dev/null 2>&1 || true
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    notify "$@"
fi
