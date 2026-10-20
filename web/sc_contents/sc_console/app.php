<?php
// == console/app.php：Console 統一入口（對應 Laravel artisan） ==
// == 用法：php console/app.php {command}——例：php console/app.php status ==
// == 原則：啟動共用（與 HTTP 同走 kernel 引導）＋分發分型（CLI 不分發 HTTP——只執行命令） ==

// sc_prelude 預載（極穩定函數層）：任何入口·最頂先載——跨版本（5.4+）·零依賴
require_once dirname(__DIR__, 2) . "/sc_prelude/load.php";

// CONFIG 層（sc_config/load.php——SC_DIR_* 等）：同 HTTP 入口（index.php）同款，喺 sc_bootstrap 之前先載
// 2026-10-14 測試發現：缺呢個預載＝SC_DIR_FRAMEWORK 未定義（CLI 一開即 Fatal）
require_once dirname(__DIR__, 2) . "/sc_config/load.php";

// 建立應用（容器——autoload/bridging 提前載入）
$app = require __DIR__ . "/../sc_bootstrap/app.php";

// 領取 Console Kernel 並執行（引導 + 分發命令）
$kernel = $app->make("console_kernel");
$kernel->run();
