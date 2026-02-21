#!/bin/bash
set -euo pipefail

PROJECT_DIR="/home/dockeradmin/projecthub"
LOG_FILE="/home/dockeradmin/deploy.log"
HEALTH_URL="http://localhost:18080"
HEALTH_RETRIES=6
HEALTH_INTERVAL=5

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "=== ProjectHub deploy started ==="

cd "$PROJECT_DIR"

# 1. Pull latest code
log "1. Pulling latest code..."
git fetch origin
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/main)
if [ "$LOCAL" = "$REMOTE" ]; then
    log "Already up to date ($LOCAL). Skipping deploy."
    exit 0
fi
git reset --hard origin/main
log "Updated: $LOCAL -> $(git rev-parse HEAD)"

# 2. Verify .env exists
if [ ! -f .env ]; then
    log "ERROR: .env file missing. Aborting."
    exit 1
fi

# 3. Rebuild and restart all containers (app, queue, scheduler)
log "3. Rebuilding containers..."
docker compose build --no-cache
docker compose down
docker compose up -d

# 4. Wait for app container to be healthy
log "4. Waiting for containers to start..."
for i in $(seq 1 $HEALTH_RETRIES); do
    STATUS=$(docker inspect --format='{{.State.Health.Status}}' projecthub 2>/dev/null || echo "not_found")
    if [ "$STATUS" = "healthy" ]; then
        log "Container healthy after $((i * HEALTH_INTERVAL))s"
        break
    fi
    if [ "$i" = "$HEALTH_RETRIES" ]; then
        log "WARNING: Container not healthy after $((HEALTH_RETRIES * HEALTH_INTERVAL))s, proceeding anyway..."
    fi
    sleep $HEALTH_INTERVAL
done

# 5. Run database migration
log "5. Running database migration..."
if docker exec projecthub php artisan migrate --force 2>&1 | tee -a "$LOG_FILE"; then
    log "Migration completed."
else
    log "WARNING: Migration may have issues. Check logs."
fi

# 6. Container status
log "6. Container status:"
docker ps --filter "name=projecthub" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" | tee -a "$LOG_FILE"

# 7. HTTP health check
log "7. HTTP health check..."
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -L "$HEALTH_URL" || echo "000")
if [ "$HTTP_STATUS" = "200" ] || [ "$HTTP_STATUS" = "302" ]; then
    log "Deploy SUCCESS. HTTP status: $HTTP_STATUS"
else
    log "Deploy WARNING. HTTP status: $HTTP_STATUS"
    exit 1
fi

log "=== ProjectHub deploy finished ==="
