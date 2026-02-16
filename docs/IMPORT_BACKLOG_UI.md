# Web UI JSON Import 功能說明

## 概述

Web UI JSON Import 功能允許管理員透過網頁介面上傳 JSON 檔案來匯入專案 backlog（專案、Epic、Task）。此功能與 CLI 命令使用相同的驗證規則和匯入邏輯。

## 訪問方式

1. 以管理員身份登入系統
2. 在導航欄點擊「Import」連結
3. 或直接訪問：`/imports/backlog`

**注意：** 此功能僅限管理員使用，非管理員用戶無法訪問。

## 使用步驟

### 1. 準備 JSON 檔案

確保您的 JSON 檔案符合以下格式：

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
      "title": "Portal-only Auth",
      "description": "Portal is IdP; Console uses one-time session_id",
      "owner_email": "user@example.com",
      "tasks": [
        {
          "title": "Admin create users (no self-register)",
          "description": "",
          "assignee_email": "user@example.com"
        }
      ]
    }
  ]
}
```

### 2. 上傳檔案

1. 點擊「選擇檔案」按鈕
2. 選擇您的 JSON 檔案（最大 2MB）
3. （可選）勾選「Dry run」選項進行預覽
4. 點擊「Import」按鈕

### 3. 查看結果

- **Dry-run 模式**：顯示藍色預覽訊息，包含將建立的專案、Epic 和 Task 數量
- **實際匯入**：顯示綠色成功訊息，包含實際建立的記錄數量和執行時間

## 檔案驗證規則

- **檔案類型**：必須是 `.json` 檔案或 `application/json` MIME 類型
- **檔案大小**：最大 2MB
- **JSON 格式**：必須是有效的 JSON 格式
- **結構要求**：必須包含 `project` 和 `epics` 欄位

## 匯入驗證規則

與 CLI 命令使用相同的驗證規則：

1. **專案代碼唯一性**
   - `project.code` 必須唯一
   - 若已存在相同 code，匯入將失敗並顯示錯誤訊息

2. **使用者驗證**
   - `owner_email` 和 `assignee_email` 必須在 users 表中存在
   - 找不到對應使用者時，匯入將失敗並顯示錯誤訊息

3. **資料庫交易**
   - 非 dry-run 模式：全部成功才 commit，任一錯誤 rollback
   - 確保資料一致性

4. **預設值策略**
   - `status`: 預設 `Backlog`
   - `priority`: 預設 `medium`
   - `agent`: 預設 `human`

## Dry-run 模式

勾選「Dry run」選項後：

- 會驗證所有規則（包括使用者存在性、專案代碼唯一性等）
- **不會**實際寫入資料庫
- 顯示預覽資訊，包含將建立的記錄數量
- 適合在正式匯入前進行測試

## 錯誤處理

系統會在以下情況顯示錯誤訊息：

1. **檔案驗證失敗**
   - 檔案不存在
   - 檔案格式不正確
   - 檔案大小超過限制

2. **JSON 格式錯誤**
   - 無效的 JSON 語法
   - 缺少必要的欄位（`project` 或 `epics`）

3. **使用者不存在**
   - JSON 中指定的 email 在 users 表中找不到

4. **專案代碼重複**
   - 嘗試匯入的專案 code 已存在

所有錯誤訊息會以紅色警示框顯示在表單上方。

## 與 CLI 命令的關係

- Web UI 和 CLI 命令使用**相同的匯入邏輯**（`BacklogImportService`）
- 驗證規則完全一致
- 兩者共享相同的錯誤處理機制
- 可以交替使用，結果一致

## 範例場景

### 場景 1: 成功匯入

1. 準備有效的 JSON 檔案
2. 上傳檔案（不勾選 dry-run）
3. 看到綠色成功訊息：
   ```
   Import completed successfully.
   Import Summary:
   - Project: 1 (PH-CORE)
   - Epics: 2
   - Tasks: 5
   - Time: 0.15s
   ```

### 場景 2: Dry-run 預覽

1. 準備 JSON 檔案
2. 勾選「Dry run」選項
3. 上傳檔案
4. 看到藍色預覽訊息：
   ```
   Dry-run completed successfully.
   Preview:
   - 1 project: PH-CORE - (preview)
   - 2 epic(s)
   - 5 task(s)
   No changes were made to the database.
   ```

### 場景 3: 錯誤處理

1. 上傳包含不存在 email 的 JSON
2. 看到紅色錯誤訊息：
   ```
   Import Error: User not found: invalid@example.com
   ```

3. 表單保留原輸入，可以修正後重新上傳

## 注意事項

- 此功能僅支援建立（create）模式，不支援更新（update）
- 所有新增欄位皆為 nullable，不影響既有功能
- 不會處理 labels、comments、notifications 等複雜欄位
- 若 JSON 內容與 DB 欄位不完全一致，會使用預設值補齊
- 建議在正式匯入前先使用 dry-run 模式進行測試
