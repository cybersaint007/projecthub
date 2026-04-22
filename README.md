# ProjectHub

A focused project management system built for AI-assisted development workflows. Organise work as **Projects → Epics → Tasks**, attach versioned AI prompts to each task, and let autonomous agents claim, execute, and report back — all gated behind a human review step before a task is marked done.

## Features

- **Task hierarchy** — Projects → Epics → Tasks, all with soft-delete and restore
- **Kanban board** — drag-drop tasks across status columns within an epic
- **AI prompt management** — versioned prompts per task and agent type (Claude Code, Cursor 2, DeepSeek, and more)
- **Agent API** — bearer-token REST API for autonomous agents to claim tasks, stream logs, upload artifacts, and update status
- **Agent Runner** — standalone PHP CLI that polls the API and dispatches tasks to the Claude Code CLI automatically
- **Review gate** — agents set tasks to `Review`; a human passes or requests changes before `Done`
- **Backlog import / export** — full round-trip JSON format for bulk-editing your backlog externally
- **Webhooks** — `task.ready` events fired to registered endpoints when tasks enter the Ready state
- **Private file storage** — upload files to tasks and projects; served through authenticated routes
- **i18n** — English and Traditional Chinese with automatic locale detection (GeoIP, Accept-Language, cookie)
- **Admin panel** — user creation (admin-only, no self-registration), project assignment, agent token management

## Requirements

- PHP 8.2+
- PostgreSQL 14+
- Node.js 18+ and npm
- Composer 2
- `claude` CLI (only needed for the Agent Runner)

## Quick Start

```bash
# 1. Clone and install dependencies
git clone https://github.com/your-org/projecthub.git
cd projecthub
composer install && npm install

# 2. Configure environment
cp .env.example .env
php artisan key:generate
# Edit .env — set DB_* at minimum

# 3. Create the PostgreSQL schema and migrate
psql -U your_user -d your_db -c "CREATE SCHEMA IF NOT EXISTS root;"
php artisan migrate

# 4. Seed demo data
php artisan db:seed

# 5. Start development servers (Laravel + queue + Vite, concurrent)
composer dev
```

Open `http://localhost:8000`.

**Seeded demo accounts:**

| Role  | Email                       | Password      | Notes |
|-------|-----------------------------|---------------|-------|
| Admin | `admin@projecthub.local`    | `admin1234`   | Full access |
| User  | `demo@projecthub.local`     | `demo1234`    | Assigned projects only |
| User  | `newuser@projecthub.local`  | `newuser1234` | Forced password change on first login |

## Configuration

| Variable | Description |
|---|---|
| `APP_URL` | Full URL of your instance |
| `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL connection |
| `DB_SCHEMA` | Schema name (default `root`) |
| `SESSION_DRIVER` | `cookie` for production |
| `CACHE_DRIVER` | `redis` for production |
| `QUEUE_CONNECTION` | `redis` for production; `sync` for local dev |
| `MAXMIND_LICENSE_KEY` | Optional — enables GeoIP-based locale detection |

## Production Deployment (Docker)

ProjectHub ships with a `Dockerfile` and `docker-compose.yml` that run three containers: **app** (PHP + Nginx), **queue worker**, and **scheduler**. PostgreSQL runs on the host.

```bash
# On the server
cp .env.example .env
# Fill in production values, then:
docker compose build --no-cache
docker compose up -d
docker exec projecthub php artisan migrate --force
```

For CI/CD via GitHub Actions (automated deploy on push to `main`), see **[docs/CI_CD_GUIDE.md](docs/CI_CD_GUIDE.md)** — it covers the full pipeline, required GitHub Secrets, and the `deploy.sh` script.

## Task Status Flow

```
Backlog / TODO → Ready → InProgress → Review → Done
                                  ↕
                               Blocked
```

Transitions are enforced in `TaskController` (web) and `AgentController` (API). Reaching `Ready` fires the `task.ready` webhook. Agent leases are auto-released when a task reaches `Review` or `Done`.

## Agent API

External workers authenticate with a Bearer token and interact with tasks through six endpoints:

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/agent/projects/{id}/tasks/next` | Find the next eligible task |
| `POST` | `/api/agent/tasks/{id}/claim` | Acquire a 60-minute exclusive lease |
| `GET` | `/api/agent/tasks/{id}/bundle` | Fetch full task context and prompt |
| `POST` | `/api/agent/tasks/{id}/logs` | Stream execution log entries |
| `POST` | `/api/agent/tasks/{id}/artifacts` | Upload output files (max 20 MB) |
| `PATCH` | `/api/agent/tasks/{id}/status` | Advance task status |

**Create a token** via `php artisan tinker`:

```php
AgentToken::create([
    'name'       => 'my-worker',
    'token'      => App\Models\AgentToken::generateToken(),
    'project_id' => 1,   // null = unrestricted across all projects
    'created_by' => 1,
]);
```

**Typical workflow:**

```
1. GET  /api/agent/projects/{id}/tasks/next?agent_type=claude_code
2. POST /api/agent/tasks/{id}/claim           { worker_id }
3. GET  /api/agent/tasks/{id}/bundle          ?lease_token=...
4. PATCH /api/agent/tasks/{id}/status         { lease_token, status: "InProgress" }
5. POST /api/agent/tasks/{id}/logs            { lease_token, level, message }   (repeated)
6. POST /api/agent/tasks/{id}/artifacts       multipart file upload             (optional)
7. PATCH /api/agent/tasks/{id}/status         { lease_token, status: "Review" }
```

Full reference: **[docs/AGENT_API.md](docs/AGENT_API.md)**

## Agent Runner

`scripts/agent-runner.php` polls ProjectHub and dispatches tasks to the `claude` CLI automatically.

**Prerequisites:** Install [Claude Code](https://docs.anthropic.com/en/docs/claude-code/getting-started) so that `claude` is available in your `PATH`.

```bash
# 1. Configure
cp scripts/agent-runner.example.env scripts/agent-runner.env
# Edit: AGENT_API_BASE, AGENT_TOKEN, AGENT_PROJECT_ID, AGENT_REPO_PATH

# 2. Run (continuous polling)
php scripts/agent-runner.php

# Preview prompt without executing
php scripts/agent-runner.php --dry-run

# Process one task then exit
php scripts/agent-runner.php --once
```

Key config options:

| Variable | Default | Description |
|---|---|---|
| `AGENT_API_BASE` | — | Base URL of your ProjectHub instance |
| `AGENT_TOKEN` | — | Bearer token from `agent_tokens` |
| `AGENT_PROJECT_ID` | — | Project to pull tasks from |
| `AGENT_REPO_PATH` | cwd | Git repo the agent works in |
| `AGENT_TYPE` | `claude_code` | Agent type filter |
| `AGENT_EPIC_ID` | (all epics) | Restrict to a specific epic |
| `AGENT_POLL_INTERVAL` | `30` | Seconds between polls when idle |
| `AGENT_CLAUDE_FLAGS` | `--dangerously-skip-permissions` | Extra flags passed to `claude` |

You can maintain multiple `.env` files for different projects or repos (e.g. `agent-runner-myproject.env`) and run each from its appropriate directory.

## Backlog Import / Export

Export the full backlog of any project as JSON from the project page, edit it externally, then re-import. The import soft-deletes existing data and rebuilds from the JSON inside a transaction, with an optional automatic backup snapshot saved to the `backlog_backups` table.

Full schema and round-trip semantics: **[docs/BACKLOG_EXPORT_IMPORT.md](docs/BACKLOG_EXPORT_IMPORT.md)**

## Internationalization

Supported locales: `en` (English) and `zh-TW` (Traditional Chinese).

Locale resolution order: `?lang=` query param → cookie (180-day) → GeoIP (MaxMind) → `Accept-Language` header → default (`en`).

To enable GeoIP detection, obtain a free [MaxMind GeoLite2](https://www.maxmind.com/en/geolite2/signup) licence key and set `MAXMIND_LICENSE_KEY` in `.env`. The deploy script downloads the database automatically.

Adding new locales: **[docs/I18N.md](docs/I18N.md)**

## User Management

ProjectHub has no self-registration. Only admins can create users.

1. Admin → **Users** → **New User** — enter name and email (no password field)
2. The system generates an 18-character password displayed **once** in a banner
3. The new account has `force_password_reset = true`; the user is redirected to `/password/change` on first login and cannot access any other page until they change it

Admins can reset any user's password the same way (Users → **Reset PW**).

## Running Tests

```bash
# All tests (SQLite in-memory — no PostgreSQL setup needed)
php artisan test

# Single test class or method
php artisan test --filter TaskControllerTest
php artisan test --filter "test_task_can_be_created"
```

## License

MIT
