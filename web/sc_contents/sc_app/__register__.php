<?php
// == sc_app/__register__.php：app 組 route 統一集中檔（由 sc_routes/app.php require 載入） ==
// == 統一管理：所有 app 的 route 集中於此——防止各 app 各自宣告造成 pattern 撞名 ==
// == route_path 一律絕對路徑（app 組 = app 目錄；執行檔慣例 {dir}/index.php） ==
// == url_path 固定結構 path（精確比對、無正則；未命中 → 404） ==
// == 設定（asset 等）仍在各 app 的 __init__.php——此檔逐個 require 載入 ==

// == hkai app（route_path 一律絕對路徑——app 組 = app 目錄） ==
sc_router()->register_routes([
    "group" => "app",
], [
    ["/", __DIR__ . "/hkai"],
]);

sc_router()->register_routes([
    "group"  => "app",
    "method" => "post",
], [
    ["/hkai", __DIR__ . "/hkai"],
]);

// == dbviewer app（Adminer 線上 sqlite 檢視——走 api 組：無 CSRF（Adminer 全部操作 POST 表單、無 token 欄位）無框架 session（自啟 adminer_sid） ==
sc_router()->register_routes([
    "group" => "api",
], [
    ["/dbviewer", __DIR__ . "/dbviewer"],
]);

// dbviewer POST（Adminer 表單操作——SQL 執行／編輯／翻頁皆 POST 回本入口）
sc_router()->register_routes([
    "group"  => "api",
    "method" => "post",
], [
    ["/dbviewer", __DIR__ . "/dbviewer"],
]);

