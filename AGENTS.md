# Repository Guidelines

## Project Structure & Module Organization
`app/` contains the Laravel application code, including controllers, models, jobs, policies, and services. HTTP and API routes live in `routes/` (`web.php`, `api.php`, `auth.php`). Blade views, Tailwind CSS, and small JavaScript modules live under `resources/`, with built assets emitted to `public/build/`. Database migrations, factories, and seeders are in `database/`. Tests are split between `tests/Feature` and `tests/Unit`. Longer operational and API documentation belongs in `docs/`.

## Build, Test, and Development Commands
Use the existing Composer scripts where possible:

- `composer setup` - install PHP and Node dependencies, create `.env` if missing, generate the app key, run migrations, and build assets.
- `composer dev` - start the local Laravel server, queue listener, log tail, and Vite dev server together.
- `composer test` - clear config and run the PHPUnit suite.
- `php artisan test --filter TaskReviewTest` - run a focused test class or method.
- `npm run build` - produce production frontend assets.
- `npm run dev` - run Vite only when you do not need the full Composer dev stack.

## Coding Style & Naming Conventions
Follow Laravel conventions and format PHP with `vendor/bin/pint`. Use 4-space indentation in PHP, `PascalCase` for classes (`TaskController`), `camelCase` for methods, and `snake_case` for database fields and migration columns. Keep Blade view names descriptive and grouped by feature (`resources/views/tasks/partials/...`). JavaScript in `resources/js/` is ES module based and should stay small and task-focused.

## Testing Guidelines
Tests use PHPUnit (`phpunit.xml`) with an in-memory SQLite database. Put request, controller, queue, and integration coverage in `tests/Feature`; keep isolated logic in `tests/Unit`. Name files `*Test.php` and prefer explicit test names around the behavior being changed. There is no declared coverage gate, so add targeted tests for every user-visible or API-visible change.

## Commit & Pull Request Guidelines
Recent history favors short, imperative commit subjects, sometimes with a scope prefix, for example `i18n: localize controller flash messages` or `Add soft delete and restore for admin user management`. Keep commits narrowly focused. PRs should summarize the behavior change, note migrations or config updates, link the relevant issue or task, and include screenshots for Blade UI changes. State the commands you ran to verify the change.

## Security & Configuration Tips
Start from `.env.example`; do not commit secrets, bearer tokens, or generated `.env` files. Treat webhook endpoints, agent tokens, and uploaded project files as sensitive. When changing deployment or CI behavior, update the matching docs in `docs/`.
