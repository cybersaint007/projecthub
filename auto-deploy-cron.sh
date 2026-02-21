#!/bin/bash
# Auto-deploy: polls GitHub for new commits on main branch.
# Intended to run via cron (e.g. every 5 minutes).
# Uses deploy.sh which skips if already up to date.

PROJECT_DIR="/home/dockeradmin/projecthub"
LOCK_FILE="/tmp/projecthub-deploy.lock"

# Prevent concurrent runs
if [ -f "$LOCK_FILE" ]; then
    LOCK_PID=$(cat "$LOCK_FILE" 2>/dev/null)
    if kill -0 "$LOCK_PID" 2>/dev/null; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Deploy already running (PID $LOCK_PID). Skipping."
        exit 0
    fi
    rm -f "$LOCK_FILE"
fi

echo $$ > "$LOCK_FILE"
trap 'rm -f "$LOCK_FILE"' EXIT

cd "$PROJECT_DIR" || exit 1

# deploy.sh handles fetch, compare, and skip-if-current
./deploy.sh
