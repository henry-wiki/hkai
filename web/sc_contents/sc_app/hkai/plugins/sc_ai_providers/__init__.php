<?php

// == sc_ai_providers plugin：provider 重生＋模型選擇資料來源 ==
// == 職責：註冊 plugin（asset css＋3 個 request action）＋可用 provider 聲明（main->initial） ==

$route      = $this->get_route();
$controller = sc_controller($route);

// provider 模型資料根（storage——來源檔＋bak 統一住 providers/model_store，唔住插件目錄）
$provider_model_store_dir = $controller->get_storage_dir() . "/providers/model_store";

$plugin = $controller->register_plugin(__DIR__, [
    // plugin settings
    "asset" => [
        __DIR__ . "/asset/sc_ai_providers.css",
    ],
]);
$plugin->setEnabled(true);

// 注入可用 provider 聲明（存在＝前台可用——provider id 為 key；值帶 provider_name＋provider_file（指向 model_store/），將來可加）
sc_ai_providers()->main()->initial([
    "available_providers" => [
        
        "openrouter" => [
            "provider_name"  => "Openrouter",
            "provider_file"  => $provider_model_store_dir . "/provider_openrouter.php",
            "models_api_url" => "https://openrouter.ai/api/v1/models?sort=coding-high-to-low",
        ],
        "kilo"       => [
            "provider_name"  => "Kilo",
            "provider_file"  => $provider_model_store_dir . "/provider_kilo.php",
            "models_api_url" => "https://api.kilo.ai/api/gateway/models",
        ],
    ],
]);

$plugin->add_request_action("provider/redownload_provider_models", __DIR__ . "/request/redownload_provider_models.php");

// 註冊 request action（model selection——modal 資料來源）
$plugin->add_request_action("provider/fetch_providers", __DIR__ . "/request/fetch_providers.php");

// 註冊 request action（單一 provider model 列表——撳左欄導航逐個載入）
$plugin->add_request_action("provider/fetch_models", __DIR__ . "/request/fetch_models.php");

// 登記 health 動作（每輪 health 檢查即時執行——回傳值丟棄、唔入回應；清舊備份：providers／benchmark model_store 逾 1 日 *.bak → 刪）
sc_ai_health()->health()->add_health_action("sweep_old_backup_files", function () use ($controller) {
    $dir_list = [
        $controller->get_storage_dir() . "/providers/model_store",
        $controller->get_storage_dir() . "/benchmark/model_store",
    ];

    return sc_ai_providers()->repo()->sweep_old_backup_files($dir_list);
});
