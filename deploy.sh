#!/bin/bash
set -euo pipefail

PROJECT_DIR="/home/dockeradmin/projecthub"
LOG_FILE="/home/dockeradmin/deploy.log"
HEALTH_URL="http://localhost:18080"
HEALTH_RETRIES=12
HEALTH_INTERVAL=5
CONTAINER_NAME="projecthub"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "=== ProjectHub deploy started ==="
cd "$PROJECT_DIR"

# 1. Pull latest code (skip if already current)
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

# 3. Rebuild and restart all containers
log "3. Rebuilding containers..."
docker compose build --no-cache
docker compose down
docker compose up -d

# 4. Wait for health
log "4. Waiting for container health..."
for i in $(seq 1 $HEALTH_RETRIES); do
    STATUS=$(docker inspect --format='{{.State.Health.Status}}' "$CONTAINER_NAME" 2>/dev/null || echo "not_found")
    if [ "$STATUS" = "healthy" ]; then
        log "Container healthy after $((i * HEALTH_INTERVAL))s"
        break
    fi
    [ "$i" = "$HEALTH_RETRIES" ] && log "WARNING: Not healthy after $((HEALTH_RETRIES * HEALTH_INTERVAL))s"
    sleep $HEALTH_INTERVAL
done

# 5. Database migration (runs inside container — reads .env natively)
log "5. Running database migration..."
docker exec "$CONTAINER_NAME" php artisan migrate --force 2>&1 | tee -a "$LOG_FILE"

# 6. Update GeoIP database (reads license key from host .env)
log "6. Updating GeoIP database..."
GEOIP_DIR="/var/www/html/storage/app/geoip"
GEOIP_FILE="$GEOIP_DIR/GeoLite2-Country.mmdb"
MAXMIND_KEY=$(grep -oP '^MAXMIND_LICENSE_KEY=\K.+' "$PROJECT_DIR/.env" 2>/dev/null | tr -d '[:space:]' || echo "")

if [ -n "$MAXMIND_KEY" ]; then
    docker exec "$CONTAINER_NAME" mkdir -p "$GEOIP_DIR"
    TMPFILE="/tmp/geoip-country.tar.gz"
    curl -sL -o "$TMPFILE" "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=${MAXMIND_KEY}&suffix=tar.gz"
    if file "$TMPFILE" | grep -q gzip; then
        EXTRACT_DIR=$(mktemp -d)
        tar xzf "$TMPFILE" -C "$EXTRACT_DIR"
        docker cp "$EXTRACT_DIR"/GeoLite2-Country_*/GeoLite2-Country.mmdb "$CONTAINER_NAME:$GEOIP_FILE"
        rm -rf "$TMPFILE" "$EXTRACT_DIR"
        log "GeoIP database updated."
    else
        log "WARNING: GeoIP download failed (invalid file). Skipping."
        rm -f "$TMPFILE"
    fi
else
    log "WARNING: MAXMIND_LICENSE_KEY not found in .env. Skipping GeoIP update."
fi

# 7. HTTP health check
log "7. HTTP health check..."
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -L "$HEALTH_URL" || echo "000")
if [ "$HTTP_STATUS" = "200" ] || [ "$HTTP_STATUS" = "302" ]; then
    log "Deploy SUCCESS. HTTP status: $HTTP_STATUS"
else
    log "Deploy FAILED. HTTP status: $HTTP_STATUS"
    exit 1
fi

log "=== ProjectHub deploy finished ==="
