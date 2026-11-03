<?php

// 載入本 plugin bridging（plugin __init__ 先於 cls func 自動載入階段——顯式載入；include_once 確保不重複）
require_once __DIR__ . "/cls/_.func.php";

$route      = $this->get_route();
$controller = sc_controller($route);

$plugin = $controller->register_plugin(__DIR__, [
    // plugin settings
    "asset" => [
        __DIR__ . "/asset/sc_ai_chats.css",
        __DIR__ . "/asset/sc_ai_messages.css",
        __DIR__ . "/asset/sc_ai_prompt.css",
        // __DIR__ . "/web/sc_contents/sc_app/hkai/plugins/sc_ai_chats/jsmods/sc_ai_chats.js",   // 停用（js 已 ESM 化——jsmods/sc_ai_chats.js 由 manifest 動態載入）
    ],
]);
$plugin->setEnabled(true);

// 訊息分頁設定注入（每頁訊息數——集中於入口提供；class 只收值）
sc_ai_chats()->message()->initial_message_page([
    "page_size" => 10,
]);

// poll 設定注入（孤兒 job 停滯門檻——集中於入口提供；class 只收值）
sc_ai_chats()->poll()->initial_poll([
    "stale_job_seconds" => 60,
]);

// 註冊 request action（存進 plugin 自己——由 controller 語境載入時收編 enabled plugins 進統一表）
$plugin->add_request_action("chat_list_fetch", __DIR__ . "/request/chat/chat_list_fetch.php");
$plugin->add_request_action("message_list_fetch", __DIR__ . "/request/message/message_list_fetch.php");
$plugin->add_request_action("prompt_fetch", __DIR__ . "/request/prompt/prompt_fetch.php");
$plugin->add_request_action("prompt_gen_random", __DIR__ . "/request/prompt/prompt_gen_random.php");
$plugin->add_request_action("prompt_send", __DIR__ . "/request/prompt/prompt_send.php");
$plugin->add_request_action("upload_attachment", __DIR__ . "/request/prompt/upload_attachment.php");
$plugin->add_request_action("message_poll", __DIR__ . "/request/message/message_poll.php");
$plugin->add_request_action("prompt_stop", __DIR__ . "/request/prompt/prompt_stop.php");
$plugin->add_request_action("prompt_resume", __DIR__ . "/request/prompt/prompt_resume.php");
$plugin->add_request_action("agent_switch", __DIR__ . "/request/prompt/agent_switch.php");
$plugin->add_request_action("queue_cancel", __DIR__ . "/request/queue/queue_cancel.php");
$plugin->add_request_action("queue_clear", __DIR__ . "/request/queue/queue_clear.php");
$plugin->add_request_action("queue_dispatch", __DIR__ . "/request/queue/queue_dispatch.php");
$plugin->add_request_action("queue_list", __DIR__ . "/request/queue/queue_list.php");
$plugin->add_request_action("message_delete", __DIR__ . "/request/message/message_delete.php");
$plugin->add_request_action("chat_delete", __DIR__ . "/request/chat/chat_delete.php");
$plugin->add_request_action("chat_archive", __DIR__ . "/request/chat/chat_archive.php");
$plugin->add_request_action("chat_restore", __DIR__ . "/request/chat/chat_restore.php");
$plugin->add_request_action("chat_item_fetch", __DIR__ . "/request/chat/chat_item_fetch.php");
$plugin->add_request_action("permission_decide", __DIR__ . "/request/permission/permission_decide.php");


$plugin->add_request_action("draft_create", __DIR__ . "/request/draft/draft_create.php");
$plugin->add_request_action("draft_fetch", __DIR__ . "/request/draft/draft_fetch.php");
$plugin->add_request_action("draft_discard", __DIR__ . "/request/draft/draft_discard.php");
$plugin->add_request_action("upload_draft_attachment", __DIR__ . "/request/attachment/upload_draft_attachment.php");
$plugin->add_request_action("request_delete_draft_attachment", __DIR__ . "/request/attachment/request_delete_draft_attachment.php");
$plugin->add_request_action("serve_attachment", __DIR__ . "/request/attachment/serve_attachment.php");
$plugin->add_request_action("draft_save_meta", __DIR__ . "/request/draft/draft_save_meta.php");

// 注入 storage 目錄（storage dir = sc_storage/{app 名}——由執行語境 controller 提供）
sc_ai_chats()->storage()->set_storage_dir($controller->get_storage_dir());
