<?php

$route      = $this->get_route();
$controller = sc_controller($route);

// 初始化 sqlite 連線及相關資訊（由 app 層 main class 集中處理——bridging 經 autoload 載入 class）
hkai__main()->initial($controller);

// 注入 runtime log 目錄（sc_storage/hkai/logs——class 只收值）
hkai__sc_runtime_io()->initial([
    "log_dir" => $controller->get_storage_dir()."/logs",
]);

// 注入設定（輕量推導（workspaceRoot/services）與使用者設定集中於入口提供；class 只收值）
// 結構性設定（filenames／folders／daemon 引擎組／server_settings）一律來自 sc_config/hkai/hkai_config.json
// env_file：機器層 env 檔完整路徑（sc_config/assets/app.php——私隱值〔API key＋執行檔〕來源；PHP／Python 共用）
// runtime_config_file：runtime.json 的準確絕對位置（入口定義——唯一動態設定來源；拉起 python 時傳入）
// fsm_map_file：FSM 機器契約（狀態機讀取——路徑只在入口定義；class 零寫死）
// about_file：關於 HKAI 資料源（唯一來源——靜態文案＋動態值皆喺該檔產生）
// version_file：版本來源（repo 根 VERSION 檔——版本唯一來源；bump 版本＝只改嗰度）
// sample_question_file：隨機範例問題題庫資料源（唯一來源——inspiration 按鈕讀取；跟 about 同形）
// python_script_dir：python 套件根（住工作區根 services/——web 外；唔隨上傳包走）
hkai__core()->initial_configs([
    "storage_dir"           => $controller->get_storage_dir(),
    "hkai_config_file"      => SC_WEB_ROOT . "/sc_config/hkai/hkai_config.json",
    "fsm_map_file"          => SC_WEB_ROOT . "/sc_config/hkai/fsm_map.json",
    "about_file"            => SC_WEB_ROOT . "/sc_config/hkai/about_hkai.php",
    "version_file"          => SC_REPO_ROOT . "/VERSION",
    "sample_question_file"  => SC_WEB_ROOT . "/sc_config/hkai/sample_question.php",
    "runtime_config_file"   => $controller->get_storage_dir()."/runtime.json",
    "python_script_dir"     => SC_REPO_ROOT . "/services",
    "env_file"              => sc_get_config("app.env_file"),
]);

// runtime 設定檢查（缺漏 → 寫 runtime log；不中斷流程）
hkai__sc_runtime()->check();



// 註冊 app 層 request action：i18n（GET 字典——供前端 JS 取同一份語系 JSON）
$controller->register_request_action("i18n", __DIR__ . "/request/i18n.php");

// 註冊 app 層 request action：set_activity／get_activity（頁面活動讀寫——activity 表）
$controller->register_request_action("set_activity", __DIR__ . "/request/set_activity.php");
$controller->register_request_action("get_activity", __DIR__ . "/request/get_activity.php");
