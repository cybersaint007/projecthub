# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Install dependencies
composer install && npm install

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
```

## Architecture

**Stack**: Laravel 12 (PHP 8.2+), PostgreSQL (`root` schema via `DB_SCHEMA` env), Blade + Alpine.js + Tailwind CSS, Vite. Tests use SQLite in-memory (`phpunit.xml`). Middleware aliases registered in `bootstrap/app.php` (not a Kernel class).

**Data hierarchy**: Project → Epic → Task (all soft-deletable). Users assigned to projects via `project_user` pivot. Admin-only user creation — no self-registration.

**Auth**: Laravel Breeze (login/logout only). `force_password_reset` on `User` triggers `ForcePasswordReset` middleware redirect to password change on first login.

**Task status flow**: `TODO | Backlog → Ready → InProgress → Review → Done` (also `Blocked`). Transitions enforced in `TaskController`; the Agent API enforces its own FSM in `AgentController` (`TODO/Ready → InProgress`, `InProgress → Review/Done`, `Review → InProgress/Done`).

**Agent types**: `claude_code`, `cursor2`, `deepseek`, `openclaw`, `human`.

**Task priorities**: Integer constants — `LOW=1`, `MEDIUM=3`, `HIGH=5`.

**Agent API** (`routes/api.php`, prefix `/api/agent`): Bearer-token auth via `AgentTokenAuth` middleware (tokens in `agent_tokens`, project-scoped or null for unrestricted). Six endpoints: `next` (find eligible task), `claim` (acquire 60-min lease), `bundle` (task context + prompt), `logs`, `artifacts`, `status`. Tasks use optimistic locking via lease fields (`leased_by`, `lease_token`, `leased_until`, `claimed_at`) to prevent double-claiming. Lease auto-clears on Review/Done transition. Expired leases recovered by `agent:recover-leases` command.

**Prompt system**: `TaskPrompt` records versioned per `(task_id, agent_type, version)` unique constraint. `AgentBundleService` resolves prompts by returning the latest version, or generating a default from task fields. All prompts get a "Work Log Report" footer appended.

**Webhooks**: Tasks reaching `Ready` status dispatch `task.ready` webhook events to registered project endpoints.

**File storage**: Private files use the `projecthub_private` disk (local, not public). Served through `ProjectFileController` with auth checks.

**Backlog import/export** (V3 JSON): `BacklogReplaceService` soft-deletes existing data and rebuilds in a transaction, saving a `BacklogBackup` snapshot first. Import uses two-pass resolution: first upserts entities (lookup by id, external_key, or code), then validates dependencies with non-fatal warnings. Soft-deleted records found via `withTrashed()` and restored rather than duplicated. Services in `app/Services/ImportV3/` and `app/Services/ExportV3/`.

**V3 field mappings** (DB ↔ JSON): `estimate_size` ↔ `estimate`, `artifact_refs` ↔ `artifacts`, `review_metadata` ↔ `review`, `assignee_value` ↔ `assignee`.

**i18n**: Locale cascade: query param `?lang=` → cookie (180-day) → GeoIP (MaxMind) → `Accept-Language` header → config default. Supported: `zh-TW`, `en`. Middleware: `DetectLocale`.

**Agent Runner** (`scripts/agent-runner.php`): Standalone CLI script that polls the Agent API and dispatches tasks to the `claude` CLI. Configure via `scripts/agent-runner.env` (copy from `scripts/agent-runner.example.env`). Runs in a loop (default 30s poll); supports `--dry-run` (preview prompt only) and `--once` (single task then exit). Multiple `.env` files can coexist for different projects (e.g. `agent-runner-housetracking.env` → run from that directory).

## CI/CD

For all CI/CD setup, deployment scripts, GitHub Actions workflows, and GitHub Secrets configuration, follow the guide at `docs/CI_CD_GUIDE.md`. This is the single source of truth for deploy scripts, Actions workflows, secrets setup, and Docker Compose patterns.

## Key Rules

- Never commit `.env` files — they contain production secrets
- Never extract credentials from `.env` via shell commands — run commands inside containers via `docker exec`
- Always use `docker compose` (space, not hyphen) for container operations
- Migrations run via `docker exec <container> php artisan migrate --force`, never via `docker run --rm`
- Tests must match actual routes — remove default scaffolding tests for routes that don't exist in the app
