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
- Admins **can** soft-delete and restore projects (with cascade to epics/tasks)
- Admins **can** import projects via JSON (CLI or Web UI)

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

## JSON Backlog Import

ProjectHub supports importing projects, epics, and tasks from JSON files via both CLI and Web UI.

### CLI Import

```bash
# Preview mode (dry-run)
php artisan projecthub:import storage/app/import-samples/backlog.minimal.sample.json --dry-run

# Actual import
php artisan projecthub:import storage/app/import-samples/backlog.minimal.sample.json
```

### Web UI Import

1. Login as Admin → navigate to **Import** in the top nav
2. Upload a JSON file (max 2MB)
3. Optionally check "Dry run" for preview
4. Click **Import**

**Features:**
- Both CLI and Web UI use the same validation logic
- Project code must be unique (even if project is soft-deleted)
- All email addresses must exist in users table
- Supports dry-run mode for preview
- Uses database transactions (all-or-nothing)

**JSON Format:**
```json
{
  "project": {
    "code": "PH-CORE",
    "name": "ProjectHub Core Build",
    "description": "Core backlog import",
    "owner_email": "user@example.com"
  },
  "epics": [
    {
      "title": "Epic Title",
      "description": "Epic description",
      "owner_email": "user@example.com",
      "tasks": [
        {
          "title": "Task Title",
          "description": "Task description",
          "assignee_email": "user@example.com"
        }
      ]
    }
  ]
}
```

See `docs/IMPORT_BACKLOG_CLI.md` and `docs/IMPORT_BACKLOG_UI.md` for detailed documentation.

---

## Project Soft Delete & Restore

Projects can be soft-deleted (not permanently removed) and restored later.

### Features

- **Soft Delete**: Projects, epics, and tasks use soft deletes (marked with `deleted_at` timestamp)
- **Cascade Delete**: Deleting a project soft-deletes all related epics and tasks
- **Cascade Restore**: Restoring a project restores all related epics and tasks
- **Code Uniqueness**: Project codes remain unique even after soft deletion (cannot be reused)
- **Member Detachment**: Project members are detached on delete (not reattached on restore)

### Usage

**Delete Project:**
1. Admin → Project detail page → Click **Delete Project**
2. Confirms deletion → Project and related epics/tasks are soft-deleted

**View Deleted Projects:**
1. Admin → Projects list → Toggle "Show deleted projects" checkbox
2. Deleted projects appear in gray with "Deleted" badge

**Restore Project:**
1. Admin → Projects list (with deleted projects visible) → Click **Restore** button
2. Or → Deleted project detail page → Click **Restore Project**
3. Project and related epics/tasks are restored

**Important:**
- Only admins can delete/restore projects
- Non-admins cannot see deleted projects
- Project codes cannot be reused even after deletion
- Project member assignments are not restored automatically

See `docs/PROJECT_DELETE_RESTORE.md` for detailed documentation.

---

## Key Routes

| Route | Description |
|-------|-------------|
| `/login` | Login page |
| `/dashboard` | Dashboard with project list |
| `/projects` | Projects list (with soft-deleted toggle for admins) |
| `/projects/{id}` | Project detail (epics + users) |
| `/projects/{id}/files` | Project files (upload/download/delete) |
| `/projects/{id}/restore` | Admin: restore soft-deleted project (POST) |
| `/epics/{id}` | Epic detail (task list) |
| `/epics/{id}/kanban` | Kanban board |
| `/tasks/{id}` | Task detail + AI Prompt Generator + Artifacts + Reviews |
| `/password/change` | Change password |
| `/admin/users` | Admin: manage users |
| `/admin/users/{id}` | Admin: user detail + project assignments |
| `/imports/backlog` | Admin: JSON backlog import (upload form) |

---

## Domain Model Overview

```
Project (1) ──→ (N) Epic (1) ──→ (N) Task (1) ──→ (N) TaskArtifact
                                      │                 TaskReview
                                      │
Project (M) ←──→ (N) User        (project_user pivot)
Project (1) ──→ (N) ProjectFile
```

**Soft Deletes:**
- `Project`, `Epic`, and `Task` models use soft deletes (`deleted_at` column)
- Soft-deleted records are hidden from default queries but can be restored
- Project codes remain unique even after soft deletion

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
├── Console/
│   └── Commands/
│       └── ProjectHubImportBacklog.php  # CLI JSON import command
├── Exceptions/
│   └── BacklogImportException.php       # Import error exception
├── Http/
│   ├── Controllers/
│   │   ├── Admin/UserController.php     # Admin user management
│   │   ├── DashboardController.php
│   │   ├── EpicController.php
│   │   ├── Import/
│   │   │   └── BacklogImportController.php  # Web UI JSON import
│   │   ├── PasswordChangeController.php
│   │   ├── ProjectController.php       # Includes soft delete/restore
│   │   ├── ProjectFileController.php
│   │   ├── TaskArtifactController.php
│   │   ├── TaskController.php
│   │   └── TaskReviewController.php
│   └── Middleware/
│       ├── AdminMiddleware.php          # Restricts /admin/* to admins
│       └── ForcePasswordReset.php       # Redirects to /password/change
├── Models/
│   ├── Epic.php                         # Uses SoftDeletes
│   ├── Project.php                      # Uses SoftDeletes
│   ├── ProjectFile.php
│   ├── Task.php                        # Uses SoftDeletes
│   ├── TaskArtifact.php
│   ├── TaskReview.php
│   └── User.php
├── Services/
│   ├── BacklogImportService.php        # Shared import logic
│   └── ImportResult.php                # Import result data class
config/
├── database.php                         # search_path = env('DB_SCHEMA')
└── filesystems.php                      # projecthub_private disk
database/
├── migrations/                          # All domain tables + soft deletes
└── seeders/DatabaseSeeder.php           # Admin + demo data
docs/
├── IMPORT_BACKLOG_CLI.md               # CLI import documentation
├── IMPORT_BACKLOG_UI.md                 # Web UI import documentation
├── IMPORT_MINIMAL_BACKLOG.md            # Original import docs
└── PROJECT_DELETE_RESTORE.md           # Soft delete/restore docs
resources/views/
├── admin/users/                         # Admin user management views
├── auth/change-password.blade.php       # Force password change
├── epics/                               # Epic CRUD + kanban
├── imports/
│   └── backlog.blade.php               # JSON import form
├── projects/                            # Project CRUD + files + delete/restore
└── tasks/                               # Task CRUD + AI prompts
storage/app/
└── import-samples/
    └── backlog.minimal.sample.json     # Sample import JSON
```
