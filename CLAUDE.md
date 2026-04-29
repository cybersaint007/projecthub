# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Install dependencies
composer install && npm install

# Full setup (install, key:generate, migrate, npm install, build)
composer run setup

# Full dev environment (Laravel serve + queue + logs + Vite, concurrent)
composer dev

# Run all tests (SQLite in-memory)
php artisan test

# Run a single test class or method
php artisan test --filter TaskControllerTest
php artisan test --filter "test_task_can_be_created"

# Lint PHP (Laravel Pint)
./vendor/bin/pint

# Build frontend assets
npm run build

# Dev server (Vite hot-reload only)
npm run dev

# Recover expired agent leases (returns abandoned tasks to Ready)
php artisan agent:recover-leases

# Test mail delivery
php artisan mail:test
```

## Architecture

**Stack**: Laravel 12 (PHP 8.2+), PostgreSQL (`root` schema via `DB_SCHEMA` env), Blade + Alpine.js + Tailwind CSS, Vite. Tests use SQLite in-memory (`phpunit.xml`). Middleware aliases registered in `bootstrap/app.php` (not a Kernel class): `admin`, `force.password.reset`, `agent.token`.

**Data hierarchy**: Project → Epic → Task (all soft-deletable). Users assigned to projects via `project_user` pivot. Admin-only user creation — no self-registration.

**Auth**: Laravel Breeze (login/logout only). `force_password_reset` on `User` triggers `ForcePasswordReset` middleware redirect to password change on first login. Authorization uses `ProjectPolicy`.

**Task status flow**: `TODO | Backlog → Ready → InProgress → Review → Done` (also `Blocked`). Transitions enforced in `TaskController`; the Agent API enforces its own FSM in `AgentController` (`TODO/Ready → InProgress`, `InProgress → Review/Done`, `Review → InProgress/Done`).

**Agent types**: `claude_code`, `cursor2`, `deepseek`, `openclaw`, `ollama`, `hermes`, `human` — defined in `Task::AGENTS` (used by the Agent API). `config/task_prompts.php` defines a separate list for prompt validation that may differ; `Task::AGENTS` is authoritative for API filtering. Prompt format types (`structured` or `raw`) are also in `config/task_prompts.php`.

**Task priorities**: Integer constants — `LOW=1`, `MEDIUM=3`, `HIGH=5`.

**Agent API** (`routes/api.php`, prefix `/api/agent`): Bearer-token auth via `AgentTokenAuth` middleware (tokens in `agent_tokens`, project-scoped or null for unrestricted). Six endpoints: `next` (find eligible task), `claim` (acquire 60-min lease), `bundle` (task context + prompt), `logs`, `artifacts`, `status`. Tasks use optimistic locking via lease fields (`leased_by`, `lease_token`, `leased_until`, `claimed_at`) to prevent double-claiming. Lease auto-clears on Review/Done transition. Expired leases recovered by `agent:recover-leases` command, which also runs on the scheduler every 5 minutes.

**Prompt system**: `TaskPrompt` records versioned per `(task_id, agent_type, version)` unique constraint. `AgentBundleService` resolves prompts by returning the latest version, or generating a default from task fields. All prompts get a "Work Log Report" footer appended.

**Webhooks**: Tasks reaching `Ready` status dispatch `task.ready` webhook events to registered project endpoints (`WebhookEndpoint` model). Delivery is a queued `DispatchWebhook` job with 3 retries and exponential backoff (60s, 300s, 900s); payloads are signed with HMAC-SHA256.

**Task reviews**: `TaskReview` model stores review records with a `truth_audit` field (structured audit checklist). Created via `TaskReviewController` at `POST /tasks/{task}/reviews`.

**Reordering**: Epics and tasks support drag/drop reordering via sortablejs. Endpoints: `POST /projects/{project}/epics/reorder` and `POST /projects/{project}/tasks/reorder`.

**Kanban board**: Per-epic kanban view at `GET /epics/{epic}/kanban` — tasks grouped by status column with drag/drop status transitions.

**Bulk agent assignment**: `PATCH /epics/{epic}/bulk-agent` updates the `agent` field on all tasks in an epic at once.

**Agent dashboard** (admin-only): `GET /agent/dashboard` — shows active leases and recent completions (last 24h) across all projects.

**Soft delete cascade**: Deleting a Project soft-deletes all its Epics and Tasks and detaches `project_user` members. Restore cascades back up (members are NOT auto-restored). Same cascade applies when deleting Epics (cascades to Tasks). See `docs/PROJECT_DELETE_RESTORE.md`.

**File storage**: Private files use the `projecthub_private` disk (local, not public). Served through `ProjectFileController` with auth checks.

**Backlog import/export** (V3 JSON): Works at both project level (`BacklogController`) and epic level (`EpicBacklogController`). `BacklogReplaceService` soft-deletes existing data and rebuilds in a transaction, saving a `BacklogBackup` snapshot first. Import uses two-pass resolution: first upserts entities (lookup by id, external_key, or code), then validates dependencies with non-fatal warnings. Soft-deleted records found via `withTrashed()` and restored rather than duplicated. Services in `app/Services/ImportV3/` and `app/Services/ExportV3/`.

**V3 field mappings** (DB ↔ JSON): `estimate_size` ↔ `estimate`, `artifact_refs` ↔ `artifacts`, `review_metadata` ↔ `review`, `assignee_value` ↔ `assignee`.

**i18n**: Locale cascade: query param `?lang=` → cookie (180-day) → GeoIP (MaxMind) → `Accept-Language` header → config default. Supported: `zh-TW`, `en`. Middleware: `DetectLocale`.

**Agent Runner** (`scripts/agent-runner.php`): Standalone CLI script that polls the Agent API and dispatches tasks to the `claude` CLI. Configure via `scripts/agent-runner.env` (copy from `scripts/agent-runner.example.env`). Runs in a loop (default 30s poll); supports `--dry-run` (preview prompt only) and `--once` (single task then exit). Multiple `.env` files can coexist for different projects (e.g. `agent-runner-housetracking.env` → run from that directory).

## CI/CD

For all CI/CD setup, deployment scripts, GitHub Actions workflows, and GitHub Secrets configuration, follow the guide at `docs/CI_CD_GUIDE.md`. This is the single source of truth for deploy scripts, Actions workflows, secrets setup, and Docker Compose patterns.

Other docs: `docs/AGENT_API.md` (full API reference), `docs/BACKLOG_EXPORT_IMPORT.md` (V3 JSON schema), `docs/IMPORT_BACKLOG_CLI.md` / `docs/IMPORT_BACKLOG_UI.md` / `docs/IMPORT_MINIMAL_BACKLOG.md` (import guides), `docs/PROJECT_DELETE_RESTORE.md` (cascade delete/restore), `docs/REVIEWER_CHECKLIST.md` (task review checklist), `docs/I18N.md` (adding locales).

## Key Rules

- Never commit `.env` files — they contain production secrets
- Never extract credentials from `.env` via shell commands — run commands inside containers via `docker exec`
- Always use `docker compose` (space, not hyphen) for container operations
- Migrations run via `docker exec <container> php artisan migrate --force`, never via `docker run --rm`
- Tests must match actual routes — remove default scaffolding tests for routes that don't exist in the app
