# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Install dependencies
composer install && npm install

# Run all tests (SQLite in-memory)
php artisan test

# Run a single test class or method
php artisan test --filter TaskControllerTest
php artisan test --filter "test_task_can_be_created"

# Lint PHP (Laravel Pint)
./vendor/bin/pint

# Build frontend assets
npm run build

# Dev server (Vite hot-reload)
npm run dev
```

## Architecture

**Stack**: Laravel 12, PostgreSQL (`root` schema via `DB_SCHEMA` env), Blade + Alpine.js + Tailwind CSS, Vite. Tests use SQLite in-memory (`phpunit.xml`).

**Data hierarchy**: Project → Epic → Task (all soft-deletable). Users assigned to projects via `project_user` pivot. Admin-only user creation — no self-registration.

**Auth**: Laravel Breeze (login/logout only). `force_password_reset` on `User` triggers a middleware redirect to password change on first login.

**Task status flow**: `Backlog → Ready → InProgress → Review → Done` (also `Blocked`). Transitions enforced in `TaskController`; the Agent API enforces its own allowed-transition set in `AgentController`.

**Agent API** (`routes/api.php`, prefix `/api/agent`): Bearer-token auth via `AgentTokenAuth` middleware, tokens stored in `agent_tokens` (project-scoped). Tasks use atomic lease fields (`leased_by`, `lease_token`, `leased_until`, `claimed_at`) to prevent double-claiming. Prompt selection logic lives in `app/Services/AgentBundleService.php`.

**File storage**: Private files use the `projecthub_private` disk (local, not public). Served through `ProjectFileController` with auth checks.

**Backlog import/export** (V3 JSON): `BacklogReplaceService` soft-deletes existing data and rebuilds in a transaction, saving a `BacklogBackup` snapshot first. Services in `app/Services/ImportV3/` and `app/Services/ExportV3/`.

**i18n**: Locale resolved via GeoIP (MaxMind DB, `config/geoip.php`) or `Accept-Language` header. Middleware in `app/Http/Middleware/`. Supported locales in `config/locale.php`.

# ProjectHub - Project Instructions

## CI/CD

For all CI/CD setup, deployment scripts, GitHub Actions workflows, and GitHub Secrets configuration, follow the guide at `docs/CI_CD_GUIDE.md`. This guide is the single source of truth for:

- deploy.sh and auto-deploy-cron.sh templates
- GitHub Actions CI/CD workflow (.github/workflows/ci.yml)
- GitHub Secrets setup (DEPLOY_HOST, DEPLOY_USER, DEPLOY_SSH_KEY)
- SSH key setup for deploy
- .env.example for CI testing
- docker-compose.yml patterns
- Common mistakes to avoid

When setting up CI/CD for this project or any new fincosoft project, read and follow `docs/CI_CD_GUIDE.md` before writing any deployment scripts or workflows.

## Key Rules

- Never commit `.env` files — they contain production secrets
- Never extract credentials from `.env` via shell commands — run commands inside containers via `docker exec`
- Always use `docker compose` (space, not hyphen) for container operations
- Migrations run via `docker exec <container> php artisan migrate --force`, never via `docker run --rm`
- Tests must match actual routes — remove default scaffolding tests for routes that don't exist in the app
