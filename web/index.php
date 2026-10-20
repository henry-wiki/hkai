<?php

// == 站點入口：setup 端點（/setup_request/）或未有完成標記 → 直入 setup.php（精靈端點完成後仍要——完成頁導覽靠佢）
// == 完成標記：setup/state/setup_done.json（setup 寫·永久保留）
// == 完成之後：正式入口——建立應用（容器）＋請求鏈（front controller——dev 同款）
// == 出口把關：全程輸出收喺 ob——離場前驗：JSON 回應而輸出唔合法 → 標 500（raw 照出——對齊 Laravel 語義）

// 出口把關（2026-10-15）：一開波收齊所有輸出（die 路徑由 shutdown 驗埋）
ob_start();

// 出口把關驗證（唯讀 buffer＋標狀態；內容照由尾段 flush——raw 照出）
$sc_guard_json_response = function ($extra_content = "") {
    // 判定：冇 buffer（已 flush 過）→ 唔理
    if (ob_get_level() === 0) {
        return;
    }

    // 判定：唔係 JSON 回應（HTML／下載等）→ 唔理
    $is_json_response = false;

    foreach (headers_list() as $header_line) {
        // 判定：Content-Type 係 JSON → 記低（以最後一個為準）
        if (stripos($header_line, "Content-Type:") === 0) {
            $is_json_response = stripos($header_line, "application/json") !== false;
        }
    }

    if (!$is_json_response) {
        return;
    }

    $response_output = ob_get_contents() . $extra_content;

    // 判定：輸出唔係合法 JSON（例：警告污染）→ 標 500（內容照出 raw）
    if ($response_output !== "" && json_decode($response_output) === null) {
        http_response_code(500);
    }
};

register_shutdown_function($sc_guard_json_response);

// sc_prelude 預載（極穩定函數層）：任何入口·最頂先載——跨版本（5.4+）·零依賴
require_once __DIR__ . "/sc_prelude/load.php";

// 配置統一入口（sc_config/load.php——路徑／檔案一切由此派生）
require __DIR__ . "/sc_config/load.php";

$setup_done = sc_get_config("setup.setup_done");

// 判定：setup 端點（網址含 /setup_request/——精靈入站動作；完成後仍要）→ 交 setup
if (strpos($_SERVER["REQUEST_URI"], "/setup_request/") !== false) {
    include __DIR__ . "/setup.php";
    return;
}

// 判定：未有完成標記 → 直入 setup（免載入 app——setup.php 自己會再驗環境）
if (!is_file($setup_done)) {
    include __DIR__ . "/setup.php";
    return;
}

// 效能計時起點（對應 Laravel 的 LARAVEL_START——測量請求處理時間用）
define("SC_START", microtime(true));

require_once __DIR__ . "/vendor/autoload.php";

// 建立應用（容器）——對齊 Laravel：$app = require bootstrap/app.php
$app = require __DIR__ . "/sc_contents/sc_bootstrap/app.php";

// 請求鏈（對齊 Laravel：$kernel->handle(Request::capture())->send()）：
$kernel   = $app->make("kernel");
$request  = sc_request()->capture();
$response = $kernel->handle($request);

// 出口把關：send 前先驗（php-cgi 送回應時提早 flush——要趁早標 500）
$sc_guard_json_response($response->getContent());

$response->send();

$kernel->terminate($request, $response);
