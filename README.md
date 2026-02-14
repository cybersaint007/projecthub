# ProjectHub

A minimal project management system built with Laravel 12, focused on **Task → AI Prompt Generation → Review Gate**.

## Tech Stack

- **Laravel 12** (PHP 8.2+)
- **PostgreSQL** with `root` schema
- **Blade** templates + Tailwind CSS + Alpine.js
- **Laravel Breeze** (customized: login/logout only, no registration)

---

## PostgreSQL Schema Setup

**Approach B** is used: the `search_path` is set in `config/database.php` via the `DB_SCHEMA` environment variable.

```php
// config/database.php → pgsql connection
'search_path' => env('DB_SCHEMA', 'public'),
```

In `.env`:
```
DB_SCHEMA=root
```

This ensures all migrations (including `users`, `sessions`, `cache`, `migrations` table itself) are created in the `root` schema.

**Prerequisite:** The `root` schema must exist in PostgreSQL:
```sql
CREATE SCHEMA IF NOT EXISTS root;
```

---

## Installation

```bash
# 1. Install PHP dependencies
composer install

# 2. Install Node dependencies & build assets
npm install && npm run build

# 3. Copy and configure environment (if not already done)
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your PostgreSQL credentials:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=projecthub
DB_USERNAME=your_user
DB_PASSWORD=your_password
DB_SCHEMA=root
```

```bash
# 4. Ensure the root schema exists in PostgreSQL
psql -U your_user -d projecthub -c "CREATE SCHEMA IF NOT EXISTS root;"

# 5. Run migrations and seed
php artisan migrate
php artisan db:seed

# 6. Start the server
php artisan serve
```

Visit: http://127.0.0.1:8000

---

## Demo Accounts

| Role | Email | Password | force_password_reset |
|------|-------|----------|---------------------|
| **Admin** | `admin@projecthub.local` | `admin1234` | No |
| User (demo) | `demo@projecthub.local` | `demo1234` | No |
| User (new) | `newuser@projecthub.local` | `newuser1234` | **Yes** (will be forced to change) |

---

## Admin: Creating Users

1. Login as Admin → navigate to **Users** in the top nav
2. Click **New User** → enter Name + Email (password field is intentionally absent)
3. Submit → the system generates an 18-character strong password
4. The password is displayed **once** in a yellow banner at the top of the users list
5. Copy it and securely deliver it to the user
6. The password is **never** stored in plain text, **never** logged

The new user's `force_password_reset` is set to `true`. On their first login, they will be redirected to `/password/change` and cannot access any other page until they set a new password.

## Admin: Resetting User Passwords

1. Go to **Users** → click **Reset PW** next to the user
2. A new password is generated and displayed once
3. The user's `force_password_reset` is set back to `true`

## Admin: Assigning Projects

1. Go to **Users** → click a user name
2. On the user detail page, check/uncheck projects
3. Click **Update Assignments**

---

## Visibility Rules

| Role | Can see | Can create/edit |
|------|---------|----------------|
| **Admin** | All projects, epics, tasks, files | Everything; manage users; assign projects |
| **User** | Only projects assigned via `project_user` + their epics/tasks/files | Tasks and epics within assigned projects |

- Users **cannot** create/delete projects (admin only)
- Users **cannot** delete epics or tasks (admin only)
- Users **can** create/edit epics and tasks within their assigned projects
- Users **can** upload files and delete **their own** uploads

---

## Project Files

### Storage
- **Disk:** `projecthub_private` → `storage/app/projecthub/`
- **Upload path:** `projects/{project_id}/YYYY/MM/filename`
- Files are stored privately — no public URL
- Downloads go through a controller route: `GET /files/{file}/download`

### Limits
- **Max file size:** 20 MB
- **Allowed types:** pdf, docx, xlsx, png, jpg, txt, md, zip

To adjust allowed types, edit `App\Models\ProjectFile::ALLOWED_EXTENSIONS`.
To adjust max size, edit `App\Models\ProjectFile::MAX_SIZE`.

### Delete Rules (Option A)
- Admin can delete **all** files
- Regular users can only delete files **they uploaded**

---

## Core Feature: AI Prompt Generator

On each **Task detail page** (`/tasks/{id}`), the system generates two AI prompts:

### Claude Code Prompt
Designed for broad changes — includes:
- Background / Context
- Goal / Instructions
- Acceptance Criteria
- Constraints (keep MVP, follow conventions)
- Deliverables (files, routes, migrations, seeds, README)

### Cursor 2 Prompt
Designed for focused, local changes — includes:
- Context summary
- Instructions
- TODO Checklist (from acceptance criteria)
- File discovery hints
- Testing instructions
- Scope limitation

Both prompts have a **Copy to Clipboard** button.

---

## Key Routes

| Route | Description |
|-------|-------------|
| `/login` | Login page |
| `/dashboard` | Dashboard with project list |
| `/projects` | Projects list |
| `/projects/{id}` | Project detail (epics + users) |
| `/projects/{id}/files` | Project files (upload/download/delete) |
| `/epics/{id}` | Epic detail (task list) |
| `/epics/{id}/kanban` | Kanban board |
| `/tasks/{id}` | Task detail + AI Prompt Generator + Artifacts + Reviews |
| `/password/change` | Change password |
| `/admin/users` | Admin: manage users |
| `/admin/users/{id}` | Admin: user detail + project assignments |

---

## Domain Model Overview

```
Project (1) ──→ (N) Epic (1) ──→ (N) Task (1) ──→ (N) TaskArtifact
                                      │                 TaskReview
                                      │
Project (M) ←──→ (N) User        (project_user pivot)
Project (1) ──→ (N) ProjectFile
```

### Task Statuses
`Backlog` → `Ready` → `InProgress` → `Review` → `Done`

### Task Agents
`claude_code` | `cursor2` | `human`

### Task Priorities
`low` | `medium` | `high`

### Artifact Types
`file_path` | `url` | `pr` | `commit` | `note`

### Review Results
`pass` | `changes_requested`

When a review passes, the task status auto-changes to `Done`.
When changes are requested, it goes back to `InProgress`.

---

## Project Structure (key files)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/UserController.php     # Admin user management
│   │   ├── DashboardController.php
│   │   ├── EpicController.php
│   │   ├── PasswordChangeController.php
│   │   ├── ProjectController.php
│   │   ├── ProjectFileController.php
│   │   ├── TaskArtifactController.php
│   │   ├── TaskController.php
│   │   └── TaskReviewController.php
│   └── Middleware/
│       ├── AdminMiddleware.php          # Restricts /admin/* to admins
│       └── ForcePasswordReset.php       # Redirects to /password/change
├── Models/
│   ├── Epic.php
│   ├── Project.php
│   ├── ProjectFile.php
│   ├── Task.php
│   ├── TaskArtifact.php
│   ├── TaskReview.php
│   └── User.php
config/
├── database.php                         # search_path = env('DB_SCHEMA')
└── filesystems.php                      # projecthub_private disk
database/
├── migrations/                          # All domain tables
└── seeders/DatabaseSeeder.php           # Admin + demo data
resources/views/
├── admin/users/                         # Admin user management views
├── auth/change-password.blade.php       # Force password change
├── epics/                               # Epic CRUD + kanban
├── projects/                            # Project CRUD + files
└── tasks/                               # Task CRUD + AI prompts
```
