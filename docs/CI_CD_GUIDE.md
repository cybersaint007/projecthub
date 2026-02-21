# CI/CD Guide for fincosoft Docker Compose Projects

This guide documents the CI/CD patterns used on the fincosoft production server (`fincosg1.fincosoft.com`). It applies to ProjectHub and should be reused as the template for future projects.

## Architecture Overview

```
Developer pushes to GitHub
        |
        v
GitHub Actions (CI: test) ──PR──> block merge if tests fail
        |
        v (on main merge)
GitHub Actions (CD: deploy) ──SSH──> production server
        |
        v
deploy.sh on server: pull → build → migrate → health check
```

Backup mechanism: a cron job polls GitHub every 5 minutes and triggers `deploy.sh` if there are new commits. This ensures deploys happen even if GitHub Actions secrets are not yet configured.

## Server Environment

- **Host:** fincosg1.fincosoft.com
- **User:** dockeradmin
- **Project base path:** `/home/dockeradmin/<project-name>`
- **Database:** PostgreSQL running on the host (not in Docker)
- **DB accessed from containers via:** `172.21.0.1` (Docker bridge gateway)
- **Container runtime:** Docker Compose

## Critical Rules

1. **`.env` is never committed.** It lives only on the server and is listed in `.gitignore`.
2. **Never extract credentials from `.env` via shell** (e.g., `grep PASSWORD .env | cut -d= -f2`). Passwords may contain `&`, `#`, `*`, etc. Instead, run commands inside the container where Laravel reads `.env` natively.
3. **Migrations run via `docker exec`**, not `docker run --rm`. The running container already has the correct `.env`, network, and volumes.
4. **All containers must be accounted for.** A typical project has `app`, `queue`, and `scheduler`. Use `docker compose up --build -d` (not standalone `docker build`) so all services are rebuilt together.
5. **`post-receive` git hooks are dead code** when the remote is GitHub. Auto-deploy is handled by cron polling or GitHub Actions SSH. The hook file can exist as a fallback but should not be relied upon.

## File Structure (per project)

```
/home/dockeradmin/<project>/
├── .env                    # Production secrets (NOT in git)
├── .env.example            # Template for CI testing (IN git)
├── .gitignore              # Must include .env
├── deploy.sh               # Main deploy script
├── auto-deploy-cron.sh     # Cron wrapper with lock file
├── docker-compose.yml      # Production compose
├── Dockerfile
└── .github/
    └── workflows/
        └── ci.yml          # GitHub Actions CI/CD
```

## deploy.sh Template

```bash
#!/bin/bash
set -euo pipefail

PROJECT_DIR="/home/dockeradmin/<project>"
LOG_FILE="/home/dockeradmin/deploy.log"
HEALTH_URL="http://localhost:<port>"
HEALTH_RETRIES=6
HEALTH_INTERVAL=5
CONTAINER_NAME="<project>"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "=== <Project> deploy started ==="
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

# 6. HTTP health check
log "6. HTTP health check..."
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -L "$HEALTH_URL" || echo "000")
if [ "$HTTP_STATUS" = "200" ] || [ "$HTTP_STATUS" = "302" ]; then
    log "Deploy SUCCESS. HTTP status: $HTTP_STATUS"
else
    log "Deploy FAILED. HTTP status: $HTTP_STATUS"
    exit 1
fi

log "=== <Project> deploy finished ==="
```

## auto-deploy-cron.sh Template

```bash
#!/bin/bash
# Cron wrapper with lock file to prevent concurrent deploys.
PROJECT_DIR="/home/dockeradmin/<project>"
LOCK_FILE="/tmp/<project>-deploy.lock"

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
./deploy.sh
```

Crontab entry:
```
*/5 * * * * /home/dockeradmin/<project>/auto-deploy-cron.sh >> /home/dockeradmin/cron-deploy.log 2>&1
```

## GitHub Actions CI/CD Template (.github/workflows/ci.yml)

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.4"
          extensions: mbstring, xml, ctype, iconv, intl, pdo, pdo_sqlite, pdo_pgsql
          coverage: none

      - name: Install Composer dependencies
        run: composer install --no-progress --prefer-dist --no-interaction

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: "20"
          cache: "npm"

      - name: Build frontend assets
        run: npm ci && npm run build

      - name: Prepare Laravel
        run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite
          php artisan migrate --force

      - name: Run tests
        run: vendor/bin/phpunit --no-coverage

  deploy:
    needs: test
    runs-on: ubuntu-latest
    if: github.event_name == 'push' && github.ref == 'refs/heads/main'
    steps:
      - name: Deploy to production
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.DEPLOY_HOST }}
          username: ${{ secrets.DEPLOY_USER }}
          key: ${{ secrets.DEPLOY_SSH_KEY }}
          script: /home/dockeradmin/<project>/deploy.sh
```

### Required GitHub Secrets

| Secret | Value |
|--------|-------|
| `DEPLOY_HOST` | `fincosg1.fincosoft.com` |
| `DEPLOY_USER` | `dockeradmin` |
| `DEPLOY_SSH_KEY` | Contents of the deploy private key (e.g., `~/.ssh/id_ed25519`) |

### Setting Up GitHub Secrets via CLI

Prerequisites: install `gh` CLI and authenticate.

```bash
# Install gh (no sudo required)
curl -sL "https://github.com/cli/cli/releases/latest/download/gh_<version>_linux_amd64.tar.gz" -o /tmp/gh.tar.gz
mkdir -p ~/bin && tar xzf /tmp/gh.tar.gz -C /tmp && cp /tmp/gh_*/bin/gh ~/bin/
export PATH="$HOME/bin:$PATH"

# Authenticate (generate token at https://github.com/settings/tokens with repo, read:org, workflow scopes)
echo "<your-token>" | ~/bin/gh auth login --with-token
```

Then set the three secrets:

```bash
REPO="fincosoft/<project>"

~/bin/gh secret set DEPLOY_HOST --repo "$REPO" --body "fincosg1.fincosoft.com"
~/bin/gh secret set DEPLOY_USER --repo "$REPO" --body "dockeradmin"
~/bin/gh secret set DEPLOY_SSH_KEY --repo "$REPO" < ~/.ssh/id_ed25519
```

### SSH Key Setup

The deploy key must be authorized to SSH into the production server. If using an existing key pair on the server:

```bash
# Verify the public key is in authorized_keys
grep -q "$(cat ~/.ssh/id_ed25519.pub)" ~/.ssh/authorized_keys || \
  cat ~/.ssh/id_ed25519.pub >> ~/.ssh/authorized_keys

# Test SSH access
ssh -o BatchMode=yes -i ~/.ssh/id_ed25519 dockeradmin@fincosg1.fincosoft.com "echo OK"
```

If creating a new dedicated deploy key:

```bash
# Generate a new key pair (no passphrase)
ssh-keygen -t ed25519 -f ~/.ssh/deploy_key -N "" -C "github-actions-deploy"

# Authorize it on the server
cat ~/.ssh/deploy_key.pub >> ~/.ssh/authorized_keys

# Use the private key as the DEPLOY_SSH_KEY secret
~/bin/gh secret set DEPLOY_SSH_KEY --repo "$REPO" < ~/.ssh/deploy_key
```

### Verifying the Secrets

```bash
# List configured secrets
~/bin/gh secret list --repo "$REPO"

# Trigger a workflow re-run to test
~/bin/gh run rerun <run-id> --repo "$REPO" --failed
```

## .env.example Template (for CI)

```env
APP_NAME=<Project>
APP_ENV=testing
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

SESSION_DRIVER=file
CACHE_DRIVER=array
QUEUE_CONNECTION=sync
```

This file is committed to git and used by CI. It uses a file-based SQLite database so CI can run migrations and tests without a real PostgreSQL instance. The CI workflow creates the SQLite file with `touch database/database.sqlite` before running `php artisan migrate --force`.

## docker-compose.yml Pattern

```yaml
services:
  app:
    build: .
    container_name: <project>
    restart: unless-stopped
    ports:
      - "127.0.0.1:<host-port>:8080"
    extra_hosts:
      - "host.docker.internal:host-gateway"
    environment:
      - APP_ENV=production
    volumes:
      - app-storage:/var/www/html/storage

  queue:
    build: .
    container_name: <project>-queue
    restart: unless-stopped
    extra_hosts:
      - "host.docker.internal:host-gateway"
    environment:
      - APP_ENV=production
    command: php artisan queue:work --sleep=3 --tries=3
    volumes:
      - app-storage:/var/www/html/storage
    depends_on:
      - app

  scheduler:
    build: .
    container_name: <project>-scheduler
    restart: unless-stopped
    extra_hosts:
      - "host.docker.internal:host-gateway"
    environment:
      - APP_ENV=production
    command: >
      sh -c "while true; do
        php artisan schedule:run --verbose --no-interaction &
        sleep 60
      done"
    volumes:
      - app-storage:/var/www/html/storage
    depends_on:
      - app

volumes:
  app-storage:
```

## Common Mistakes to Avoid

| Mistake | Why it breaks | Correct approach |
|---------|--------------|-----------------|
| `grep PASSWORD .env \| cut -d= -f2` | Passwords with `&`, `#`, `*` get truncated or cause shell errors | Run commands inside container via `docker exec` |
| `docker run --rm` for migrations | Requires manual env var passing, not on Docker network | `docker exec <container> php artisan migrate --force` |
| `docker build -t name:latest .` then `docker compose up` | Compose doesn't use standalone-built images; builds its own | Use `docker compose build` or `docker compose up --build` |
| Relying on `post-receive` hook | Only fires for bare repo push targets; GitHub remotes bypass it | Use cron polling or GitHub Actions SSH deploy |
| `docker-compose` (hyphenated) | Deprecated; may not exist on newer Docker installs | Use `docker compose` (space, plugin form) |
| Committing `.env` to git | Leaks secrets | `.env` in `.gitignore`; use `.env.example` for CI |

## New Project Checklist

1. Create project directory: `/home/dockeradmin/<project>/`
2. Set up `Dockerfile` and `docker-compose.yml`
3. Create `.env` on server with production credentials (never commit)
4. Create `.env.example` in git for CI testing (SQLite + file session)
5. Copy and customize `deploy.sh` and `auto-deploy-cron.sh`
6. Add crontab entry: `*/5 * * * * /home/dockeradmin/<project>/auto-deploy-cron.sh >> /home/dockeradmin/cron-deploy.log 2>&1`
7. Create `.github/workflows/ci.yml` (include Node.js build step if using Vite)
8. Ensure deploy SSH key is in `~/.ssh/authorized_keys` on the server
9. Configure GitHub Secrets via `gh secret set` (see "Setting Up GitHub Secrets via CLI" above)
10. Ensure tests match actual routes — remove scaffolding tests for routes that don't exist
11. Test: push a commit, verify CI passes, verify deploy job SSHes in and runs `deploy.sh`
