<?php

// == migrations/0003_add_message_tool_call.sql.php：schema v3
// == 內容：message_tool_call 表＋索引（工具調用記錄——每條命令一行，start／done 靠 upsert 合併）
// == 上游：資料庫遷移（舊庫補表）；新裝由 schema 檔直接建

return <<<'SQL'
CREATE TABLE IF NOT EXISTS message_tool_call (
  tool_call_id   VARCHAR(64) PRIMARY KEY,
  project_id     INTEGER NOT NULL DEFAULT 0,
  chat_id        INTEGER NOT NULL DEFAULT 0,
  message_id     INTEGER NOT NULL DEFAULT 0,
  response_index INTEGER NOT NULL DEFAULT 0,
  seq            INTEGER NOT NULL DEFAULT 0,
  tool_name      VARCHAR(120) NOT NULL DEFAULT '',
  input_text     TEXT NOT NULL DEFAULT '',
  output_text    TEXT NOT NULL DEFAULT '',
  status         VARCHAR(32) NOT NULL DEFAULT '',
  started_at     DATETIME NOT NULL DEFAULT '',
  finished_at    DATETIME NOT NULL DEFAULT ''
);

CREATE INDEX IF NOT EXISTS idx_tool_call_message ON message_tool_call (message_id, response_index, seq);
SQL;
