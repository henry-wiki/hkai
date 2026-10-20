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
        // __DIR__ . "/asset/sc_ai_chats.js",   // 停用（js 已 ESM 化——jsmods/sc_ai_chats.js 由 manifest 動態載入）
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

// 登記 health 報告（name＝輸出 JSON key——重複名會即時報錯）
// == 2026-09-28 暫停（pause）：get_all_poll_jobs 掃 messages/polling/ 目錄（實測 5–18ms＋fsm 域首載 4–16ms）；報告零讀者——
// == 「要唔要 polling」前端已用 reports.chat.polling_count（chats.polling_msg，O(1)）；polling 啟動另有 prompt_send／message_list_fetch 兩路。恢復＝解註釋
// sc_ai_health()->health()->add_health_report("message_poll", function ($request_data) {
//     return sc_ai_chats()->health()->get_poll_report($request_data);
// });

sc_ai_health()->health()->add_health_report("chat", function ($request_data) {
    return sc_ai_chats()->health()->get_chat_report($request_data);
});

// 登記 health 動作（每輪 health 檢查即時執行——回傳值丟棄、唔入回應；收尾孤兒 job：狀態檔已終態但冇人收尾）
sc_ai_health()->health()->add_health_action("finalize_stale_job", function () {
    return sc_ai_chats()->poll()->finalize_stale_job();
});

// 登記 health 動作（每輪 health 檢查即時執行——回傳值丟棄、唔入回應；清 old archive：已歸檔訊息目錄逾 1 日 → 整個刪）
sc_ai_health()->health()->add_health_action("sweep_old_archived_messages", function () {
    return sc_ai_chats()->poll()->sweep_old_archived_messages();
});

// 外部貢獻 status item（chat 欄右側——歸檔入口／離開歸檔；provider HTML——Closure 於 render 時求值）
sc_ai_status_bar()->status_bar()->add_status_item("chat", function () {
    // 當前專案 slug（連結目標：/<project_segment>/archive/——歸檔列表入口）
    $project_slug = sc_ai_projects()->project()->get_selected_project_slug();
    
    // 判定：冇專案語境（slug 為空）→ 唔出歸檔入口（防 //archive/ 協議相對 URL）
    if (
        $project_slug === null
        || $project_slug === ""
    ) {
        return "";
    }
    
    // 當前專案段（歸檔形 archive@<project_slug>——連結保住歸檔語境；段知識住 build_project_segment）
    $project_row     = sc_ai_projects()->project()->get_project_row($project_slug);
    $project_status  = $project_row["project_status"];
    $project_segment = sc_ai_projects()->project()->build_project_segment($project_slug, $project_status);

    // 依語境定目標（歸檔語境＝離開歸檔（箭頭圖示）／普通＝入歸檔（盒仔圖示）——後台出；URL 一變由 status bar 區重渲更新）
    if (sc_ai_chats()->chat()->is_archive_chat_segment(sc_get_friendly(1))) {
        $archive_url   = "/" . $project_segment . "/";
        $archive_title = hkai__i18n()->get("chat.archive_leave");
        $archive_icon  = "fa-solid fa-arrow-left";
    } else {
        $archive_url   = "/" . $project_segment . "/archive/";
        $archive_title = hkai__i18n()->get("chat.archive");
        $archive_icon  = "fa-solid fa-box-archive";
    }
    
    return "<a class='sc_statusbar__archive' href='" . $archive_url . "' title='" . $archive_title . "'><i class='" . $archive_icon . "'></i></a>";
});

// 註冊 request action（存進 plugin 自己——由 controller 語境載入時收編 enabled plugins 進統一表）
$plugin->add_request_action("chat_list_fetch", __DIR__ . "/request/chat/chat_list_fetch.php");
$plugin->add_request_action("message_list_fetch", __DIR__ . "/request/message/message_list_fetch.php");
$plugin->add_request_action("prompt_fetch", __DIR__ . "/request/prompt/prompt_fetch.php");
$plugin->add_request_action("prompt_gen_random", __DIR__ . "/request/prompt/prompt_gen_random.php");
$plugin->add_request_action("prompt_send", __DIR__ . "/request/prompt/prompt_send.php");
$plugin->add_request_action("message_poll", __DIR__ . "/request/message/message_poll.php");
$plugin->add_request_action("prompt_stop", __DIR__ . "/request/prompt/prompt_stop.php");
$plugin->add_request_action("daemon_engine_switch", __DIR__ . "/request/prompt/daemon_engine_switch.php");
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

// 注入 storage 目錄（storage dir = sc_storage/{app 名}——由執行語境 controller 提供）
sc_ai_chats()->storage()->set_storage_dir($controller->get_storage_dir());
