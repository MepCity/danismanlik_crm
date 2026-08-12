#!/bin/sh

set -eu

notify_failure() {
    message=$1
    echo "[operasyon] HATA: ${message}" >&2

    if [ -n "${ALERT_WEBHOOK_URL:-}" ]; then
        payload=$(jq -nc --arg text "Bizlife CRM: ${message}" '{text: $text}')
        curl --fail --silent --show-error \
            -H 'Content-Type: application/json' \
            --data "${payload}" \
            "${ALERT_WEBHOOK_URL}" >/dev/null || true
    fi
}
require_variable() {
    variable=$1
    eval "value=\${${variable}:-}"

    if [ -z "${value}" ]; then
        notify_failure "${variable} boş olamaz"
        exit 1
    fi
}

initialize_repository() {
    require_variable RESTIC_REPOSITORY
    require_variable RESTIC_PASSWORD

    if ! restic snapshots >/dev/null 2>&1; then
        restic init
    fi
}
