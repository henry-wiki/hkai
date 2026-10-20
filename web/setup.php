<?php

// == HKAI setup：獨立入口（同 index.php 平排·同一層）
// == 職責：入即檢（唔經 index.php／vendor／framework）——版本閘 → 交 load.php（載入＋開檢）
// == 本檔留無 namespace（版本閘要喺未支援 namespace 嘅舊 PHP 都 parse 得到；核心 cls 全檔 namespace sc\setup、語法下限 5.4）

// sc_prelude 預載（極穩定函數層）：任何入口·最頂先載——跨版本（5.4+）·零依賴
require_once __DIR__ . "/sc_prelude/load.php";

// 版本閘（最先——太舊＝單張靜態畫面；核心都唔載）
if (version_compare(PHP_VERSION, "5.4", "<")) {
    header("Content-Type: text/html; charset=UTF-8");

    $gate_lines   = array();
    $gate_lines[] = "<!DOCTYPE html>";
    $gate_lines[] = "<html lang='zh-Hant'>";
    $gate_lines[] = "<head>";
    $gate_lines[] = "<meta charset='UTF-8'>";
    $gate_lines[] = "<title>HKAI setup</title>";
    // 版本閘畫面嘅 CSS 網址（老 PHP 未支援 namespace——唔可以叫 cls class；就地由站根砌）
    $gate_css_disk  = str_replace("\\", "/", __DIR__ . "/setup/assets/setup.css");
    $gate_site_root = str_replace("\\", "/", dirname(__DIR__));
    $gate_css_url   = substr($gate_css_disk, strlen($gate_site_root));
    $gate_lines[] = "<link rel='stylesheet' href='" . $gate_css_url . "'>";
    $gate_lines[] = "</head>";
    $gate_lines[] = "<body>";
    $gate_lines[] = "<div class='setup_gate'>";
    $gate_lines[] = "<h1>PHP 版本太舊</h1>";
    $gate_lines[] = "<p>你的 PHP 版本是 " . PHP_VERSION . "——檢查畫面需要 PHP 5.4 或以上，運行本體需要 7.3 或以上。</p>";
    $gate_lines[] = "<p>請升級 PHP 後再訪。</p>";
    $gate_lines[] = "</div>";
    $gate_lines[] = "</body>";
    $gate_lines[] = "</html>";

    echo implode("\r\n", $gate_lines);
    exit;
}

// 配置統一入口（sc_config/load.php——路徑／檔案一切由此派生）
require __DIR__ . "/sc_config/load.php";

// 交載入閘（load.php——快檢完成標記 → 條件載入 cls → 出畫面＋開檢）
include_once __DIR__ . "/setup/load.php";
