# Project Delete & Restore 功能說明

## 概述

ProjectHub 支援專案的軟刪除（Soft Delete）和恢復功能。當專案被刪除時，相關的 Epics 和 Tasks 也會被軟刪除，並且可以通過恢復專案來一併恢復。

## 軟刪除（Soft Delete）的意義

軟刪除不會真正從資料庫中移除記錄，而是：
- 在記錄上標記 `deleted_at` 時間戳
- 記錄在預設查詢中不可見
- 記錄可以通過恢復功能重新啟用
- 資料完整性得以保留

## 功能特性

### 1. 級聯刪除（Cascade Delete）

當專案被軟刪除時：
- **專案本身**：標記為已刪除
- **所有相關 Epics**：自動軟刪除
- **所有相關 Tasks**：自動軟刪除（透過 Epics）
- **專案成員關聯（project_user）**：自動解除關聯（detach）

**注意：** 專案成員關聯在恢復時**不會**自動重新建立。這是為了保持簡單性，管理員需要手動重新分配成員。

### 2. 級聯恢復（Cascade Restore）

當專案被恢復時：
- **專案本身**：恢復為正常狀態
- **所有相關 Epics**：自動恢復
- **所有相關 Tasks**：自動恢復（透過 Epics）
- **專案成員關聯**：**不會**自動恢復（需手動重新分配）

### 3. 專案代碼唯一性

**重要：** `project.code` 的唯一性約束在軟刪除後仍然有效。即使專案被軟刪除：
- 相同的 `project.code` **無法**被重新使用
- 嘗試匯入相同 code 的專案會失敗
- 這確保了專案代碼的永久唯一性

## 權限要求

- **刪除專案**：僅限管理員
- **恢復專案**：僅限管理員
- **查看已刪除專案**：僅限管理員
- **非管理員用戶**：無法看到或操作已刪除的專案

## 使用方式

### 刪除專案

1. 以管理員身份登入
2. 進入專案詳情頁面
3. 點擊「Delete Project」按鈕
4. 確認刪除操作
5. 專案及其相關 Epics 和 Tasks 將被軟刪除

### 查看已刪除專案

1. 以管理員身份登入
2. 在專案列表頁面，點擊「View Deleted Projects」連結
3. 或直接訪問：`/projects?trashed=1`
4. 已刪除的專案會以灰色顯示，並標記「Deleted」標籤

### 恢復專案

1. 以管理員身份登入
2. 進入已刪除專案的詳情頁面（從已刪除專案列表）
3. 點擊「Restore Project」按鈕
4. 確認恢復操作
5. 專案及其相關 Epics 和 Tasks 將被恢復

## 資料庫結構

### 新增欄位

以下表已添加 `deleted_at` 欄位（nullable timestamp）：
- `projects`
- `epics`
- `tasks`

### Migration

執行以下 migration 來添加軟刪除支援：

```bash
php artisan migrate
```

Migration 檔案：`database/migrations/2026_02_16_060848_add_soft_deletes_to_projects_epics_tasks.php`

## 技術實現

### 模型

所有相關模型已啟用 `SoftDeletes` trait：
- `App\Models\Project`
- `App\Models\Epic`
- `App\Models\Task`

### 控制器方法

**ProjectController@destroy**
- 使用資料庫交易確保一致性
- 級聯軟刪除所有相關記錄
- 解除專案成員關聯

**ProjectController@restore**
- 使用資料庫交易確保一致性
- 級聯恢復所有相關記錄
- 需要手動重新分配專案成員

### 路由

- `DELETE /projects/{project}` - 刪除專案
- `POST /projects/{project}/restore` - 恢復專案

## UI 變更

### 專案列表頁面

- 管理員可以看到「View Deleted Projects」連結
- 已刪除專案以灰色顯示，標記「Deleted」
- 顯示刪除時間（例如：「Deleted 2 days ago」）

### 專案詳情頁面

- **未刪除專案**：顯示「Delete Project」按鈕（僅管理員）
- **已刪除專案**：顯示「Restore Project」按鈕（僅管理員）
- 已刪除的 Epics 以灰色顯示，標記「Deleted」

## 測試步驟

### 測試 1: 刪除專案

1. 以管理員身份登入
2. 建立一個測試專案（包含 Epics 和 Tasks）
3. 進入專案詳情頁面
4. 點擊「Delete Project」
5. 確認專案從列表中消失
6. 訪問 `/projects?trashed=1` 查看已刪除專案

**預期結果：**
- 專案被軟刪除
- 相關 Epics 和 Tasks 也被軟刪除
- 專案成員關聯被解除

### 測試 2: 恢復專案

1. 以管理員身份登入
2. 訪問 `/projects?trashed=1`
3. 點擊已刪除的專案
4. 點擊「Restore Project」
5. 確認專案恢復到正常列表

**預期結果：**
- 專案被恢復
- 相關 Epics 和 Tasks 也被恢復
- 專案成員需要手動重新分配

### 測試 3: 專案代碼唯一性

1. 建立一個專案，code 為 "TEST-001"
2. 刪除該專案
3. 嘗試匯入相同 code 的專案（透過 CLI 或 Web UI）

**預期結果：**
- 匯入失敗，錯誤訊息：「Project with code 'TEST-001' already exists. Import stopped.」

### 測試 4: 權限驗證

1. 以非管理員身份登入
2. 嘗試訪問已刪除專案的 URL
3. 嘗試直接訪問恢復路由

**預期結果：**
- 返回 403 Forbidden 錯誤
- 無法看到刪除/恢復按鈕

## 注意事項

1. **專案成員關聯**：刪除時會解除，恢復時**不會**自動重新建立
2. **專案代碼**：即使專案被刪除，code 仍然保留唯一性約束
3. **資料完整性**：所有軟刪除操作都在資料庫交易中執行，確保一致性
4. **查詢行為**：預設查詢不會包含已刪除的記錄，需要使用 `withTrashed()` 或 `onlyTrashed()`

## 相關檔案

- Migration: `database/migrations/2026_02_16_060848_add_soft_deletes_to_projects_epics_tasks.php`
- Controller: `app/Http/Controllers/ProjectController.php`
- Models: `app/Models/Project.php`, `app/Models/Epic.php`, `app/Models/Task.php`
- Views: `resources/views/projects/index.blade.php`, `resources/views/projects/show.blade.php`
- Routes: `routes/web.php`
