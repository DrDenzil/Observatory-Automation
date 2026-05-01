#!/usr/bin/env bash
# Notify script - sends error notifications via Bayfordbury API
# Usage: notify.sh "Title" "Message"

API_URL="https://147.197.221.254/api/notification.php"
API_KEY="9okEap1xDT2mVR3k"

if [[ $# -lt 2 ]]; then
    echo "Usage: $0 \"Title\" \"Message\""
    exit 1
fi

title="$1"
message="$2"

# URL encode (basic)
title_encoded=$(echo "$title" | sed 's/ /+/g;s/&/%26/g')
message_encoded=$(echo "$message" | sed 's/ /+/g;s/&/%26/g')

# Send notification
curl -s "${API_URL}?p=${API_KEY}&title=${title_encoded}&message=${message_encoded}" > /dev/null

exit 0
