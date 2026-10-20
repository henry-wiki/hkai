<?php

// == setup/assets/database_schema.php：資料庫表結構（schema 唯一來源——建立 DB 用） ==
// == 純值檔：只放結構定義（零邏輯、零外部引用）——建立 DB 時逐條 exec ==
// == 來源：由最終結構（dev sc-hkai.test 嘅 sqlite db）抽出原句，重製為冪等（CREATE IF NOT EXISTS） ==
// == 消費：setup/cls/sections/database/database.db_ready.php（setup_install） ==

return [
    // 資料表（CREATE TABLE——抽出時原句＋IF NOT EXISTS）
    "tables"  => [
        "CREATE TABLE IF NOT EXISTS projects (
  project_id integer NULL PRIMARY KEY AUTOINCREMENT,
  project_slug text NOT NULL,
  project_name text NOT NULL,
  project_path text NOT NULL,
  provider_id text NOT NULL,
  model_id text NOT NULL,
  reasoning_effort text NOT NULL,
  chat_mode text NOT NULL DEFAULT '',
  project_sort_index integer NOT NULL DEFAULT '0',
  project_created_at numeric NOT NULL,
  project_updated_at numeric NOT NULL,
  project_status text NOT NULL DEFAULT 'active',
  tech_stack text NOT NULL,
  git_remote_url text NOT NULL,
  description text NOT NULL,
  auto_slug integer NOT NULL DEFAULT '0'
)",
        "CREATE TABLE IF NOT EXISTS chats (
  chat_id integer NULL PRIMARY KEY AUTOINCREMENT,
  project_id integer NOT NULL DEFAULT '0',
  chat_sid text NOT NULL,
  chat_title text NOT NULL,
  chat_tags text NOT NULL,
  provider_id text NOT NULL,
  model_id text NOT NULL,
  reasoning_effort text NOT NULL,
  chat_mode text NOT NULL DEFAULT '',
  chat_message_count integer NOT NULL DEFAULT '0',
  chat_created_at numeric NOT NULL,
  chat_updated_at numeric NOT NULL
, chat_status VARCHAR(32) NOT NULL DEFAULT 'normal', completed_msg INTEGER NOT NULL DEFAULT 0, failed_msg INTEGER NOT NULL DEFAULT 0, cancelled_msg INTEGER NOT NULL DEFAULT 0, polling_msg INTEGER NOT NULL DEFAULT 0)",
        "CREATE TABLE IF NOT EXISTS messages (
            message_id        INTEGER PRIMARY KEY AUTOINCREMENT,
            chat_id           INTEGER NOT NULL,
            project_id        INTEGER NOT NULL DEFAULT 0,
            message_content   TEXT NOT NULL DEFAULT '',
            message_status    VARCHAR(255) NOT NULL DEFAULT '',
            message_created_at DATETIME NOT NULL DEFAULT '',
            message_update_at DATETIME NOT NULL DEFAULT ''
        , message_chat_mode VARCHAR(255) NOT NULL DEFAULT '', message_provider_id VARCHAR(255) NOT NULL DEFAULT '', message_model_id VARCHAR(255) NOT NULL DEFAULT '', message_started_at DATETIME NOT NULL DEFAULT '', message_finished_at DATETIME NOT NULL DEFAULT '', message_elapsed_sec INTEGER NOT NULL DEFAULT 0, message_meta TEXT NOT NULL DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS meta (
                meta_id        INTEGER PRIMARY KEY AUTOINCREMENT,
                meta_key       VARCHAR(255) NOT NULL DEFAULT '',
                meta_value     TEXT NOT NULL DEFAULT '',
                meta_created_at DATETIME NOT NULL DEFAULT '',
                meta_updated_at DATETIME NOT NULL DEFAULT ''
            )",
        "CREATE TABLE IF NOT EXISTS message_response (
    response_id         INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id          INTEGER NOT NULL DEFAULT 0,
    chat_id             INTEGER NOT NULL DEFAULT 0,
    message_id          INTEGER NOT NULL DEFAULT 0,
    poll_file           VARCHAR(60) NOT NULL DEFAULT '',
    response_type       VARCHAR(255) NOT NULL DEFAULT '',
    response_index      INTEGER NOT NULL DEFAULT 0,
    response_content    TEXT NOT NULL DEFAULT '',
    response_status     VARCHAR(32) NOT NULL DEFAULT '',
    response_started_at DATETIME NOT NULL DEFAULT '',
    response_finished_at DATETIME NOT NULL DEFAULT '',
    response_created_at DATETIME NOT NULL DEFAULT '',
    response_updated_at DATETIME NOT NULL DEFAULT ''
)",
        "CREATE TABLE IF NOT EXISTS activity (
            activity_id        INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id         INTEGER NOT NULL DEFAULT 0,
            chat_id            INTEGER NOT NULL DEFAULT 0,
            activity_at        DATETIME NOT NULL DEFAULT ''
            , activity_name VARCHAR(255) NOT NULL DEFAULT '', activity_content TEXT NOT NULL DEFAULT '')",
    ],

    // 索引（CREATE (UNIQUE) INDEX——抽出時原句＋IF NOT EXISTS）
    "indexes" => [
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_meta_key ON meta (meta_key)",
        "CREATE INDEX IF NOT EXISTS idx_messages_chat_id ON messages (chat_id)",
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_projects_slug ON projects (project_slug)",
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_message_response_type ON message_response (message_id, response_type, response_index)",
        "CREATE INDEX IF NOT EXISTS idx_message_response_chat ON message_response (chat_id)",
        "CREATE INDEX IF NOT EXISTS idx_message_response_poll_file ON message_response (poll_file)",
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_activity_name_scope ON activity (activity_name, project_id, chat_id) WHERE activity_name <> ''",
    ],
];
