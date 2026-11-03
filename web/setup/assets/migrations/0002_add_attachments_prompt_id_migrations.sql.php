<?php

// == migrations/0002_add_attachments_prompt_id_migrations.sql.php：schema v2（0.2.0）
// == 內容：attachments 表＋索引；messages.prompt_id；migrations 版本記錄表
// == 上游：importer（舊庫 v1 → v2）；新裝由 schema 檔直接建

return <<<'SQL'
CREATE TABLE IF NOT EXISTS attachments (
  attachment_id integer NULL PRIMARY KEY AUTOINCREMENT,
  project_id integer NOT NULL DEFAULT '0',
  chat_id integer NOT NULL DEFAULT '0',
  message_id integer NOT NULL DEFAULT '0',
  filepath text NOT NULL DEFAULT '',
  filename text NOT NULL DEFAULT '',
  original_filename text NOT NULL DEFAULT '',
  mime_type text NOT NULL DEFAULT '',
  file_size integer NOT NULL DEFAULT '0',
  file_hash text NOT NULL DEFAULT '',
  image_width integer NOT NULL DEFAULT '0',
  image_height integer NOT NULL DEFAULT '0',
  content text NOT NULL DEFAULT '',
  attachment_status text NOT NULL DEFAULT 'pending',
  attachment_created_at datetime NOT NULL DEFAULT '',
  attachment_updated_at datetime NOT NULL DEFAULT ''
);

-- 0.2.0：messages 加 prompt_id（對應 prompt 記錄）
ALTER TABLE messages ADD COLUMN prompt_id text NOT NULL DEFAULT '';

CREATE TABLE IF NOT EXISTS migrations (
  migration_name text NOT NULL PRIMARY KEY,
  migration_checksum text NOT NULL DEFAULT '',
  migration_applied_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_attachments_message ON attachments (message_id);
CREATE INDEX IF NOT EXISTS idx_attachments_project ON attachments (project_id);
CREATE INDEX IF NOT EXISTS idx_attachments_hash ON attachments (file_hash);
SQL;
