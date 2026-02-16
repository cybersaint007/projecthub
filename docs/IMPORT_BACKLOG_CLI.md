# CLI JSON Import 功能說明

## 概述

`projecthub:import` 是一個 Artisan 命令，用於從 JSON 檔案匯入專案 backlog（專案、Epic、Task）。此功能採用 **Minimal 模式**，僅支援建立（create）操作，不支援更新（update）。

**注意：** 此命令與 Web UI Import 功能使用相同的匯入邏輯（`BacklogImportService`），確保驗證規則和行為完全一致。

## 命令語法

```bash
php artisan projecthub:import {file} [--dry-run]
```

### 參數

- `{file}`: JSON 檔案路徑（必填）

### 選項

- `--dry-run`: 預覽模式，只顯示將寫入的筆數與預覽，不實際寫入資料庫

## JSON 格式

```json
{
  "project": {
    "code": "PH-CORE",
    "name": "ProjectHub Core Build",
    "description": "Core backlog import",
    "owner_email": "james.lee@fincosoft.com"
  },
  "epics": [
    {
      "title": "Portal-only Auth",
      "description": "Portal is IdP; Console uses one-time session_id",
      "owner_email": "james.lee@fincosoft.com",
      "tasks": [
        {
          "title": "Admin create users (no self-register)",
          "description": "",
          "assignee_email": "james.lee@fincosoft.com"
        }
      ]
    }
  ]
}
```

### 欄位說明

#### Project
- `code` (必填): 專案代碼，必須唯一
- `name` (必填): 專案名稱
- `description` (選填): 專案描述
- `owner_email` (選填): 專案擁有者 email，必須存在於 users 表

#### Epic
- `title` (必填): Epic 標題
- `description` (選填): Epic 描述
- `owner_email` (選填): Epic 擁有者 email，必須存在於 users 表
- `tasks` (選填): Task 陣列

#### Task
- `title` (必填): Task 標題
- `description` (選填): Task 描述
- `assignee_email` (選填): Task 負責人 email，必須存在於 users 表

## 行為規則

1. **專案代碼唯一性**
   - `project.code` 必須唯一
   - 若已存在相同 code，直接報錯停止（不做 update）

2. **使用者驗證**
   - `owner_email` 和 `assignee_email` 必須在 users 表中存在
   - 找不到對應使用者時報錯停止（含 dry-run 也會驗證）

3. **資料庫交易**
   - 非 dry-run 模式：全部成功才 commit，任一錯誤 rollback

4. **預設值策略**
   - `status`: 預設 `Backlog`
   - `priority`: 預設 `medium`
   - `agent`: 預設 `human`
   - `due_date`/`estimate`/`order`: JSON 沒給就 null

5. **輸出**
   - dry-run: 顯示將新增 project/epics/tasks 數量
   - 非 dry-run: 顯示完成後新增數量 + 花費時間

## 使用範例

### 範例 1: 預覽模式（dry-run）

```bash
php artisan projecthub:import storage/app/import-samples/backlog.minimal.sample.json --dry-run
```

輸出範例：
```
Validating users...
=== DRY RUN MODE ===

Will create:
  - 1 project: PH-CORE - ProjectHub Core Build
  - 1 epic(s)
  - 1 task(s)

Dry-run completed. No changes were made to the database.
```

### 範例 2: 實際匯入

```bash
php artisan projecthub:import storage/app/import-samples/backlog.minimal.sample.json
```

輸出範例：
```
Validating users...
Starting import...

✓ Created project: PH-CORE - ProjectHub Core Build
  ✓ Created epic: Portal-only Auth

=== Import Completed ===
Project: 1
Epics: 1
Tasks: 1
Time: 0.15s
```

### 範例 3: 重複匯入（會報錯）

```bash
php artisan projecthub:import storage/app/import-samples/backlog.minimal.sample.json
```

輸出範例（第二次執行）：
```
Validating users...
Starting import...

Import failed: Project with code 'PH-CORE' already exists. Import stopped.
```

## 錯誤處理

命令會在以下情況報錯並停止：

1. **檔案不存在**
   ```
   Import failed: File not found: {file_path}
   ```

2. **JSON 格式錯誤**
   ```
   Import failed: Invalid JSON: {error_message}
   ```

3. **JSON 結構錯誤**
   ```
   Import failed: Invalid JSON structure: missing "project" or "epics"
   ```

4. **使用者不存在**
   ```
   Import failed: User not found: {email}
   ```

5. **專案代碼重複**
   ```
   Import failed: Project with code '{code}' already exists. Import stopped.
   ```

## 與 Web UI 的關係

- CLI 命令和 Web UI 使用**相同的匯入邏輯**（`BacklogImportService`）
- 驗證規則完全一致
- 兩者共享相同的錯誤處理機制
- 可以交替使用，結果一致

## Schema Mapping Summary

### JSON → Database 欄位對應

| JSON 欄位 | Database 欄位 | 表 | 備註 |
|----------|--------------|-----|------|
| `project.code` | `code` | `projects` | 必填，唯一 |
| `project.name` | `name` | `projects` | 必填 |
| `project.description` | `description` | `projects` | 選填 |
| `project.owner_email` | `owner_id` (FK) | `projects` | 透過 email 查詢 users.id |
| `epic.title` | `title` | `epics` | 必填 |
| `epic.description` | `description` | `epics` | 選填 |
| `epic.owner_email` | `owner_id` (FK) | `epics` | 透過 email 查詢 users.id |
| `task.title` | `title` | `tasks` | 必填 |
| `task.description` | `description` | `tasks` | 選填 |
| `task.assignee_email` | `assignee_id` (FK) | `tasks` | 透過 email 查詢 users.id |
| - | `status` | `tasks` | 預設值: `Backlog` |
| - | `priority` | `tasks` | 預設值: `medium` |
| - | `agent` | `tasks` | 預設值: `human` |

### 新增的資料庫欄位

為了支援此功能，新增了以下 nullable 欄位（不影響既有功能）：

- `projects.code` (string, nullable, unique)
- `projects.owner_id` (foreignId, nullable, FK to users)
- `epics.owner_id` (foreignId, nullable, FK to users)
- `tasks.assignee_id` (foreignId, nullable, FK to users)

## 測試步驟

### 前置準備

1. **執行 Migration**
   ```bash
   php artisan migrate
   ```
   這會新增以下欄位：
   - `projects.code` (nullable, unique)
   - `projects.owner_id` (nullable, FK to users)
   - `epics.owner_id` (nullable, FK to users)
   - `tasks.assignee_id` (nullable, FK to users)

2. **確保有測試使用者**
   確保 JSON 中使用的 email 對應的使用者已存在於 `users` 表中。

### 測試 1: Dry-run 模式

```bash
php artisan projecthub:import storage/app/import-samples/backlog.minimal.sample.json --dry-run
```

**預期結果：**
- 顯示將建立的 project/epics/tasks 數量
- 不實際寫入資料庫

### 測試 2: 實際匯入

```bash
php artisan projecthub:import storage/app/import-samples/backlog.minimal.sample.json
```

**預期結果：**
- 成功建立 project、epic 和 task
- 顯示完成統計（數量 + 時間）

### 測試 3: 重複匯入（應報錯）

再次執行相同的匯入命令：

```bash
php artisan projecthub:import storage/app/import-samples/backlog.minimal.sample.json
```

**預期結果：**
- 因為 `project.code` 已存在而報錯
- 錯誤訊息：`Project with code 'PH-CORE' already exists. Import stopped.`

### 測試 4: 使用者不存在（應報錯）

修改 JSON 中的 email 為不存在的使用者，然後執行：

```bash
php artisan projecthub:import storage/app/import-samples/backlog.minimal.sample.json --dry-run
```

**預期結果：**
- 在驗證階段就報錯
- 錯誤訊息：`User not found: {email}`

## 注意事項

- 此功能僅支援建立（create）模式，不支援更新（update）
- 所有新增欄位皆為 nullable，不影響既有功能
- 不會處理 labels、comments、notifications 等複雜欄位
- 若 JSON 內容與 DB 欄位不完全一致，會使用預設值補齊
- 與 Web UI Import 功能共享相同的驗證規則和匯入邏輯
