# 更新記錄

## 0.2.0 — 2026-11-04

0.2.0 係架構級改版，唔係功能補丁。訊息管線重建成「單一 Python bridge＋兩條 SSE 通道（state／workspace）」；代理層收窄為 Goose（ACP）；訊息紀錄新增工具調用歷史；產品變成自足——內建 Apache／PHP、前端 library 全部本地、i18n 字典嵌入頁面。aider 引擎、健康模組同所有 CDN 依賴已移除。

### 新增

- **串流狀態層**：新增 `state_stream` 同 `workspace_stream` 兩條 SSE，配合新 `sc_state_watch` 域——把伺服器快照投影落 store、三層 DOM 同單一寫入點嘅 CSS 狀態；取代各模組自己輪詢嘅做法。
- **工具調用紀錄**：`msg_response.N.tools.jsonl` 解析入 DB，前端以 `seg--tool` 段呈現，一條命令一格，每格可複製原始命令。
- **Prompt draft 層**：`data/messages/draft/{project}/{chat}`，支援單次 multipart 上傳（13 張圖）、`preparing → promote` 抽取鏈，以及獨立工作目錄嘅 `image_extractor` 服務。
- **Bridge 服務**（`services/bridge`）：生命週期台帳＋心跳、presence／串流連線登記、每訊息附件串流（`/bridge_sse_for_message`）。
- **可攜外殼**：`execute/HKAI.exe` 啟動內建 Apache 2.4.54＋PHP-CGI 8.4.26（mod_fcgid），由 `bin/custom/conf/00-paths.conf` 接駁；唔需要系統 PHP 或 vhost。
- **離線前端**：jQuery、Bootstrap、Font Awesome、marked、DOMPurify、highlight.js、Mermaid、SweetAlert2、CodeMirror、toastr 全部收入 `web/sc_contents/sc_resources/vendor`。
- **i18n 嵌入**：字典由 PHP 出頁時寫入頁面，消除開頁時顯示原始 key 嘅競態；71 處硬編碼字串已轉 key。
- **安裝精靈**：獨立 `web/setup`，支援全新安裝同升級（暫存複本、資料庫匯入），並檢查 fcgid 上傳上限。
- **診斷**：`sc_console` 分 scope 記錄＋由心跳檔驅動嘅程序檢視。

### 變更

- **伺服器生命週期**：單一入口（`start_server`）；health 串流只監視唔起動；手動停止會寫 `data/bridge/server_stopped_by_user`，避免自動重起。
- **隊列語義**：每個對話同時只跑一條，完成後自動接下一條排隊訊息。
- **Worker 管理**：`goose_daemon` 拆成 manager／worker 兩層；派工前先查心跳，唔會再把工單交去正在閒置退出嘅 worker。
- **訊息輪詢**：`message_poll` 收窄成完成時一次抓取；即時內容由每訊息嘅 SSE 串流提供。
- **Git 狀態**：由 state 串流搬去 workspace 串流。
- **Prompt 解析**：統一經 `resolve_project_slug`、`resolve_chat_sid`、`is_valid_chat_sid`。
- **資料庫**：schema 升到 `dbv-1.1`；升級時舊庫（連 `-wal`／`-shm`）改名成時間戳備份後才建新庫。
- **環境偵測**：改為 in-process，移除 `%SystemDrive%` 幽靈資料夾；機器路徑只留 `.env`。

### 移除

- **aider 引擎**：`services/aider_daemon`、`aider_entry`、litellm hooks、`externals/aider`，以及 `.env` 嘅 `aider_pylibs_dir`。
- **`sc_ai_health`** 插件同 `/health` 端點。
- **`sc_ai_setup`** 插件——安裝改由 `web/setup` 負責。
- **`server_status`** 端點同設定頁嘅啟動輪詢。
- **前端 CDN 依賴**（jsDelivr、cdnjs）同 `php -S` 開發伺服器模式。
