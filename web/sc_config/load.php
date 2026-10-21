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
    "config"          => [
        "hkai_settings" => SC_WEB_ROOT . "/sc_config/hkai/settings.json",
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
