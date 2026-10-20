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

// ---------- 根事實（唯一原始計算——define 一次；其餘全部由它派生） ----------
define("SC_WEB_ROOT", str_replace("\\", "/", rtrim(dirname(__DIR__, 1), "/")));
define("SC_REPO_ROOT", dirname(SC_WEB_ROOT));
define("SC_ENV_FILE", SC_REPO_ROOT . "/.env");
