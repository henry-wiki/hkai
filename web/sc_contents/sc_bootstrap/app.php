<?php


// == sc_bootstrap/app.php：建立應用（容器）並回傳——index.php require 它 ==
// == 對齊 Laravel bootstrap/app.php（return $app）——入口唯一：index.php（HTTP）／sc_console/app.php（CLI） ==

// 提前載入 bridging（定義 sc_application()/sc_autoload()/sc_kernel() 等全域函數）
require_once SC_DIR_FRAMEWORK . "/foundation/_.func.php";
// http bridging 提前載入（sc_request() 供 index.php 入口 capture 用——Laravel 入口 Request::capture 同款）
require_once SC_DIR_FRAMEWORK . "/http/_.func.php";

sc_autoload()->register()->add_prefix([
    "sc\\framework" => SC_DIR_FRAMEWORK,
    "sc\\app"       => SC_DIR_APP,
    "sc\\api"       => SC_DIR_API,
]);



// 建立應用（bridging 統一入口——getInstance 快取；basePath 預設 SC_DOCUMENT_ROOT）
$app = sc_application(SC_DOCUMENT_ROOT);

// 綁定（容器輕量版：resolver 延遲執行，run() 內 make 才呼叫）
$app->singleton("kernel", function () {
    return sc_kernel();
});

// ExceptionHandler（急救員）——對應 Laravel Contracts\Debug\ExceptionHandler
// 作用：錯誤統一處理（sc_error——現役 bridging）——綁進容器讓「錯誤處理」也走容器
$app->singleton("error", function () {
    return sc_error();
});

// Console Kernel（後勤經理）——對應 Laravel Contracts\Console\Kernel
// 作用：CLI 入口（console/ 目錄——php console/{command}.php）——命令列請求處理
$app->singleton("console_kernel", function () {
    return sc_console_kernel();
});

// 選擇性 debug 收尾（Laravel $app->terminating() 對應——保持 debug.php 為空 = 關閉）
require_once __DIR__ . "/debug.php";

return $app;
