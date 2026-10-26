<?php
// == init.php：全站最早執行——環境初始化＋根事實（header／ini_set 屬副作用、無法 return） ==
// == 由 load.php 最先 require（任何入口經 load.php＝環境先就位） ==

// 編碼 header——只在尚未送出時設定（重複載入＝跳過）
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

ini_set('display_errors', 1);

ini_set('precision', 12);
ini_set('serialize_precision', 12);

// ---------- FastCGI 路徑正規化（單一入口——Apache 交 //./G:/… 裝置前綴） ----------
// 原因：Apache SetHandler 用 proxy:fcgi://…//./ → SCRIPT_FILENAME／DOCUMENT_ROOT 帶 //./；
//       PHP 由此派生嘅路徑（含子程序 chdir）會壞。喺最早一步洗乾淨，之後全站唔會再見。
$sc_server_path_keys = ["SCRIPT_FILENAME", "PATH_TRANSLATED", "DOCUMENT_ROOT", "ORIG_SCRIPT_FILENAME"];

foreach ($sc_server_path_keys as $sc_server_path_key) {
    // 判定：冇呢個 key → 跳過
    if (!isset($_SERVER[$sc_server_path_key])) {
        continue;
    }

    $sc_server_path_value = str_replace("\\", "/", $_SERVER[$sc_server_path_key]);

    // 判定：帶 //./ 前綴（裝置形）→ 剝走
    if (strpos($sc_server_path_value, "//./") === 0) {
        $sc_server_path_value = substr($sc_server_path_value, 4);
    }

    // 判定：帶 //?/ 前綴（UNC 裝置形）→ 剝走
    if (strpos($sc_server_path_value, "//?/") === 0) {
        $sc_server_path_value = substr($sc_server_path_value, 4);
    }

    $_SERVER[$sc_server_path_key] = $sc_server_path_value;
}

// ---------- 根事實（唯一原始計算——define 一次；其餘全部由它派生） ----------
$sc_web_root = str_replace("\\", "/", rtrim(dirname(__DIR__, 1), "/"));

// Apache FastCGI 帶 //./ 裝置前綴（//./G:/…）→ 剝走（否則 chdir／子程序 cwd 會壞）
if (strpos($sc_web_root, "//./") === 0) {
    $sc_web_root = substr($sc_web_root, 4);
}

define("SC_WEB_ROOT", $sc_web_root);
define("SC_REPO_ROOT", dirname(SC_WEB_ROOT));
define("SC_ENV_FILE", SC_REPO_ROOT . "/.env");
