# Backlog Export/Import Replace

## Overview

ProjectHub supports exporting a project's entire backlog (epics, tasks, and prompts) as JSON, and replacing the backlog from JSON. This enables:

- Backup and restore of project structure
- Bulk editing via JSON (export, modify, re-import)
- Migrating backlog between environments
- AI-assisted backlog generation (generate JSON, then import)

## Export

### Endpoint
`GET /projects/{project}/backlog/export-json`

### Access
Any user with access to the project (owner, editor, viewer, or admin).

### Output
Downloads a JSON file named `{project_code}_backlog_{YYYYMMDD_HHMM}.json`.

### Exported Fields

**Project:** `code`, `name`, `description`

**Epics:** `title`, `description`, `milestone_tag`, `position`

**Tasks (all user-editable fields from Task::$fillable):**
- `position`, `title`, `description`, `status`, `agent`, `priority`
- `tags` (array), `context`, `instructions`, `acceptance_criteria`

**Task Prompts (if any exist):**
- `agent_type`, `format_type`, `title`, `version`, `content`

### Example Export
```json
{
  "version": "2.0",
  "exported_at": "2026-03-01T10:30:00+00:00",
  "project": {
    "code": "PH",
    "name": "ProjectHub",
    "description": "Project management app"
  },
  "epics": [
    {
      "title": "Authentication",
      "description": "User auth system",
      "milestone_tag": "v1.0",
      "position": 10,
      "tasks": [
        {
          "position": 10,
          "title": "Login page",
          "description": "Build login form",
          "status": "Done",
          "agent": "claude_code",
          "priority": 5,
          "tags": ["frontend"],
          "context": "...",
          "instructions": "...",
          "acceptance_criteria": "...",
          "prompts": [
            {
              "agent_type": "claude_code",
              "format_type": "structured",
              "title": "Login Implementation",
              "version": 1,
              "content": "..."
            }
          ]
        }
      ]
    }
  ]
}
```

## Import Replace

### UI Location
Project show page → "Import/Replace" button (visible to owners, editors, admins).

### Page: `/projects/{project}/backlog/import-replace`

The import replace page provides:
1. **Textarea** for pasting JSON
2. **File upload** for JSON files
3. **Preview** button - shows what will change before applying
4. **Apply Replace** button - executes the replacement
5. **Backup checkbox** (default: checked) - saves current backlog before replacing

### Workflow

1. **Paste or upload** JSON data
2. Click **Preview Changes** - shows current vs incoming counts
3. Review the preview (epics/tasks counts, deltas)
4. Optionally uncheck "Create backup" (not recommended)
5. Click **Apply Replace** to execute

### Replace Semantics

The replace operation runs in a database transaction:

1. **Backup** (if enabled): Exports current backlog as JSON and saves to `backlog_backups` table
2. **Soft-delete**: All existing epics and tasks are soft-deleted (preserving history, logs, artifacts)
3. **Create**: New epics and tasks are created from the JSON
4. **Prompts**: If the JSON includes `prompts` arrays, TaskPrompt records are created

### Validation

The JSON is validated for:
- Required `epics` array
- Each epic must have a `title`
- Each task must have a `title`
- Task `status` must be valid (TODO, Backlog, Ready, InProgress, Review, Done)
- Task `agent` must be valid (claude_code, codex, cursor2, deepseek, openclaw, ollama, hermes, human)
- Task `priority` must be 1, 3, or 5

### Round-Trip Guarantee

Export → Import Replace → Export produces identical structure and ordering:
- `Epic.position` and `Task.position` are preserved exactly
- All user-editable fields are round-tripped
- Prompts are preserved if included in the export

### Endpoints
- `GET /projects/{project}/backlog/import-replace` - Form page
- `POST /projects/{project}/backlog/import-replace/preview` - Preview changes
- `POST /projects/{project}/backlog/import-replace/apply` - Execute replacement

### Access
Owners, editors, and admins can import/replace.

## Database Changes

New table `backlog_backups`:
- `id`
- `project_id` (FK)
- `created_by` (FK, nullable)
- `payload_json` (jsonb) - Full export JSON snapshot
- `note` (string, nullable)
- `timestamps`

## Backup Recovery

Backups are stored in the `backlog_backups` table. To restore from a backup:

```php
$backup = App\Models\BacklogBackup::where('project_id', $projectId)->latest()->first();
$service = app(App\Services\BacklogReplaceService::class);
$service->replace($project, $backup->payload_json, userId: null, backup: true);
```
