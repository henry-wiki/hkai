<?php
// == sc_config/load.php：配置統一入口（任何入口 require 佢＝環境＋配置＋常數全部就位） ==
// == 流程＝init.php（環境初始化＋根事實）→ sc_set_configs（大陣列交入倉）→ legacy.php（常數兼容層） ==

require_once __DIR__ . "/init.php";

// config 域函數（sc_config()／sc_get_config()／sc_set_config()／sc_set_configs()——class 隨入）
require_once __DIR__ . "/cls/sc_config.func.php";

// env 域函數（sc_env()／sc_get_env()／sc_set_env()——class 隨入）
require_once __DIR__ . "/cls/sc_env.func.php";


// ---------- 統一配置（大陣列——sc_set_configs 交入倉；sc_get_config／sc_get_configs 取） ----------
sc_set_configs([
    "web_root"        => SC_WEB_ROOT,
    "repo_root"       => SC_REPO_ROOT,

    "core"            => [
        "env"         => SC_ENV_FILE,
        "env_example" => SC_REPO_ROOT . "/.env.example",
        "version"     => SC_REPO_ROOT . "/VERSION",
    ],

    "contents"        => [
        "dir"       => SC_WEB_ROOT . "/sc_contents",
        "framework" => SC_WEB_ROOT . "/sc_contents/sc_framework",
        "app"       => SC_WEB_ROOT . "/sc_contents/sc_app",
        "api"       => SC_WEB_ROOT . "/sc_contents/sc_api",
        "request"   => SC_WEB_ROOT . "/sc_contents/sc_request",
        "console"   => SC_WEB_ROOT . "/sc_contents/sc_console",
        "channel"   => SC_WEB_ROOT . "/sc_contents/sc_channel",
        "main"      => SC_WEB_ROOT . "/sc_contents/sc_app/hkai",
        "resources" => SC_WEB_ROOT . "/sc_contents/sc_resources",
        "projects"  => SC_WEB_ROOT . "/projects",
    ],

    "storage"         => [
        "root_dir"      => SC_WEB_ROOT . "/sc_storage",
        "app_dir"       => dirname(SC_WEB_ROOT) . "/data",
        "database_file" => dirname(SC_WEB_ROOT) . "/data/localdb/hkai_chat_data.sqlite",
    ],
    "app"             => [
        "sc_contents_dir" => SC_WEB_ROOT . "/sc_contents",
        "plugin_hkai_dir" => SC_WEB_ROOT . "/sc_contents/sc_app/hkai",
    ],
    "hkai"            => [
        // 預設專案 slug（Home——建立／fallback／禁改禁刪一律讀呢個，唔再散落字面值）
        "default_project_slug" => "home",
        "config_file"          => SC_WEB_ROOT . "/sc_config/hkai/hkai_config.json",
        // 出廠默認（唯讀——setup／安裝流程只讀寫呢份）
        "settings"             => SC_WEB_ROOT . "/sc_config/hkai/settings.json",
        "fsm_map_file"         => SC_WEB_ROOT . "/sc_config/hkai/fsm_map.json",
        "about_file"           => SC_WEB_ROOT . "/sc_config/hkai/about_hkai.php",
        "sample_question_file" => SC_WEB_ROOT . "/sc_config/hkai/sample_question.php",
        "runtime_config_file"  => dirname(SC_WEB_ROOT) . "/data/runtime.json",

        // 資料目錄（站台 data/——PHP／Python 共用；絕對路徑單一來源）
        "messages_dir"             => dirname(SC_WEB_ROOT) . "/data/messages",
        "messages_archive_dir"     => dirname(SC_WEB_ROOT) . "/data/messages/archive",
        "messages_polling_dir"     => dirname(SC_WEB_ROOT) . "/data/messages/polling",
        "messages_attachments_dir" => dirname(SC_WEB_ROOT) . "/data/messages/attachments",
        "messages_draft_dir"       => dirname(SC_WEB_ROOT) . "/data/messages/draft",
        "messages_queue_dir"       => dirname(SC_WEB_ROOT) . "/data/messages/queue",
        // 附件上傳上限（單位 KB——PHP 同前端共用嘅單一來源）
        "attachments" => [
            "max_upload_size" => 20000,
        ],
        // 草稿（draft）設定——可調區（前端由 DOM data-* 讀同一份值）
        "draft" => [
            "max_upload_size"        => 20000, // 單檔上限 KB
            "max_source_side_px"     => 12800, // 原圖長邊上限（超過＝拒收）
            "resize_target_side_px"  => 2500,   // 長邊超過此值 → 縮到此值（≤ 此值＝原樣）
            "thumb_side_px"          => 640,   // 草稿縮圖長邊（伺服器產生）
            "max_images_per_message" => 13,
        ],
        "image_extract_dir"        => dirname(SC_WEB_ROOT) . "/data/_tmp/image_extract",
        "process_status_dir"       => dirname(SC_WEB_ROOT) . "/data/process_status",
        "stream_dir"               => dirname(SC_WEB_ROOT) . "/data/stream",
        "localdb_dir"              => dirname(SC_WEB_ROOT) . "/data/localdb",
        "log_dir"                  => dirname(SC_WEB_ROOT) . "/data/logs",
        "instructions_dir"         => dirname(SC_WEB_ROOT) . "/data/instructions",
        "bridge_dir"               => dirname(SC_WEB_ROOT) . "/data/bridge",
        "activity_dir"             => dirname(SC_WEB_ROOT) . "/data/activity",

        // Home 專案沙盒目錄（project_slug＝home 的工作路徑；沒有 repo、供零設定試用）
        "playground_dir"            => dirname(SC_WEB_ROOT) . "/data/playground",
        "providers_dir"             => dirname(SC_WEB_ROOT) . "/data/providers",
        "providers_model_store_dir" => dirname(SC_WEB_ROOT) . "/data/providers/model_store",
        "benchmark_model_store_dir" => dirname(SC_WEB_ROOT) . "/data/benchmark/model_store",

        // 出廠默認指示檔（唯讀源——每次 ensure 覆寫入 data/instructions；用戶唔可以改）
        "instructions" => [
            "system_env"    => SC_WEB_ROOT . "/sc_config/hkai/instructions/system_env.md",
            "system_prompt" => SC_WEB_ROOT . "/sc_config/hkai/instructions/system_prompt.md",
        ],

        // 出廠默認文本（public＝可公開、唯讀源——唔會俾用戶改）
        "public" => [
            "process_status" => [
                "process_list" => dirname(SC_WEB_ROOT) . "/data/process_status/process_list.jsonl",
            ],
            // 用戶覆蓋（可寫——用戶改設定只寫呢份）
            "settings" => dirname(SC_WEB_ROOT) . "/data/settings.json",
        ],
    ],
    "view"            => [
        "logo"  => SC_WEB_ROOT . "/setup/assets/hkai-logo.png",
    ],
    "setup"           => [
        "lang_dir"      => SC_WEB_ROOT . "/setup/cls/i18n/lang",
        "state_dir"     => SC_WEB_ROOT . "/setup/state",
        "setup_done"    => SC_WEB_ROOT . "/setup/state/setup_done.json",
        "setup_process" => SC_WEB_ROOT . "/setup/state/setup_process.json",
    ],

    "services"        => SC_REPO_ROOT . "/services",
    "externals"       => [
        "aider" => SC_REPO_ROOT . "/externals/aider/pylibs",
        "goose" => SC_REPO_ROOT . "/externals/goose/goose.exe",
    ],

    // 運行時（工作區根 web/ 上一層——session／route log 用）
    "runtime"         => [
        "tmp" => SC_REPO_ROOT . "/_tmp",
        "log" => SC_REPO_ROOT . "/_log",
    ],
]);


// 時區設定（.env APP_TIMEZONE 可覆寫；空值／未設＝Asia/Hong_Kong）——configs 就位後設（sc_env 讀 core.env）
$app_timezone = sc_get_env("APP_TIMEZONE", "");

// 判定：空值（.env 預留空行）→ 用預設（空字串會蓋過 default——唔可以直傳）
if (trim($app_timezone) === "") {
    $app_timezone = "Asia/Hong_Kong";
}

date_default_timezone_set($app_timezone);

// 常數兼容層（SC_* 等——只此一處 include，一次即夠）
require_once __DIR__ . "/legacy.php";
