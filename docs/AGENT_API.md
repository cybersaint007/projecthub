# Agent API

The Agent API allows external workers (on different machines) to fetch tasks, execute prompts, and report results back to ProjectHub without using the web UI.

## Authentication

All Agent API endpoints require a bearer token. Tokens are stored in the `agent_tokens` table and can be scoped to a specific project or be global (project_id = null).

```
Authorization: Bearer <token>
```

Tokens can be created by admins via Tinker or a future admin UI:

```php
App\Models\AgentToken::create([
    'name' => 'macmini-worker',
    'token' => App\Models\AgentToken::generateToken(),
    'project_id' => 1, // or null for all projects
    'created_by' => 1,
]);
```

## Endpoints

All endpoints are prefixed with `/api/agent`.

### 1. GET /api/agent/projects/{project}/tasks/next

Find the next eligible task for execution.

**Query Parameters:**
- `worker_id` (optional) - Identifier for the worker
- `agent_type` (optional) - Filter by agent type: `claude_code`, `cursor2`, `deepseek`, `openclaw`, `human`

**Eligibility:**
- Status must be `TODO` or `Ready`
- No active lease (leased_until is null or expired)
- Returns earliest by position, then by id

**Response:**
```json
{
  "task": {
    "id": 42,
    "title": "Implement user auth",
    "status": "TODO",
    "agent": "claude_code",
    "priority": 5,
    "position": 10
  }
}
```

Returns `{"task": null}` if no eligible tasks found.

### 2. POST /api/agent/tasks/{task}/claim

Claim a task for execution. Uses atomic locking to prevent duplicate claims.

**Body:**
```json
{
  "worker_id": "macmini-m2-dev"
}
```

**Response (200):**
```json
{
  "lease_token": "abc123...",
  "leased_until": "2026-03-01T15:00:00+00:00",
  "task_id": 42
}
```

**Response (409):** Task already claimed by another worker.

Leases last 60 minutes by default. After expiry, another worker can reclaim the task.

### 3. GET /api/agent/tasks/{task}/bundle?lease_token=...

Fetch the full task bundle for execution.

**Query Parameters:**
- `lease_token` (required) - From the claim response

**Response:**
```json
{
  "project": {
    "id": 1,
    "code": "PH",
    "name": "ProjectHub",
    "description": "..."
  },
  "epic": {
    "id": 5,
    "title": "Authentication",
    "description": "...",
    "milestone_tag": "v1.0",
    "position": 10
  },
  "task": {
    "id": 42,
    "title": "Implement login",
    "description": "...",
    "status": "TODO",
    "agent": "claude_code",
    "priority": 5,
    "tags": ["backend"],
    "context": "...",
    "instructions": "...",
    "acceptance_criteria": "...",
    "position": 10,
    "assignee_id": null,
    "created_at": "...",
    "updated_at": "..."
  },
  "prompt": {
    "id": 12,
    "agent_type": "claude_code",
    "format_type": "structured",
    "title": "Auth Implementation",
    "version": 3,
    "content": "Full prompt content...",
    "generated": false
  }
}
```

**Prompt Selection Logic:**
1. Looks for the latest `TaskPrompt` matching `(task_id, agent_type=task.agent)` by highest version
2. If found: returns that prompt with `generated: false`
3. If not found: generates a default prompt from task.context + task.instructions + task.acceptance_criteria + task.description, returns with `generated: true`

### 4. POST /api/agent/tasks/{task}/logs

Post execution logs.

**Body:**
```json
{
  "lease_token": "abc123...",
  "level": "info",
  "message": "Compilation completed successfully"
}
```

Levels: `info`, `error`, `warning`, `debug`

**Response (201):**
```json
{
  "id": 99
}
```

### 5. POST /api/agent/tasks/{task}/artifacts

Upload artifacts (multipart form).

**Fields:**
- `lease_token` (required)
- `file` (required) - The file to upload (max 20MB)
- `kind` (optional) - Category string
- `note` (optional) - Description

Allowed file types: pdf, docx, xlsx, png, jpg, txt, md, zip

**Response (201):**
```json
{
  "id": 15,
  "stored_path": "projects/1/2026/03/filename.txt"
}
```

### 6. PATCH /api/agent/tasks/{task}/status

Update task status.

**Body:**
```json
{
  "lease_token": "abc123...",
  "status": "InProgress"
}
```

**Allowed Transitions:**
| From | To |
|------|-----|
| TODO | InProgress |
| Ready | InProgress |
| InProgress | Review, Done |
| Review | InProgress, Done |

Setting status to `Done` or `Review` automatically releases the lease.

**Response:**
```json
{
  "task_id": 42,
  "status": "InProgress"
}
```

## Typical Worker Flow

```
1. GET  /api/agent/projects/1/tasks/next?agent_type=claude_code
2. POST /api/agent/tasks/42/claim          { worker_id: "mac-1" }
3. GET  /api/agent/tasks/42/bundle?lease_token=abc123
4. PATCH /api/agent/tasks/42/status        { lease_token: "abc123", status: "InProgress" }
5. POST /api/agent/tasks/42/logs           { lease_token: "abc123", level: "info", message: "..." }
6. POST /api/agent/tasks/42/artifacts      (multipart: lease_token + file)
7. PATCH /api/agent/tasks/42/status        { lease_token: "abc123", status: "Review" }
```

## Database Changes

Migration adds to `tasks` table:
- `leased_by` (string, nullable) - Worker identifier
- `lease_token` (string(64), nullable, indexed) - Active lease token
- `leased_until` (timestamp, nullable) - Lease expiry
- `claimed_at` (timestamp, nullable) - When claimed

New table `agent_tokens`:
- `id`, `name`, `token` (unique), `project_id` (nullable FK), `created_by` (FK), `last_used_at`, `expires_at`, `timestamps`
