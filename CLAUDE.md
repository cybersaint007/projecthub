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
