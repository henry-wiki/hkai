<?php

// == sc-hkai URL rewrite（php -S 專用——代替 .htaccess）
// == 位置：sites\sc-hkai\url_rewrite.php
// == 用法：php.exe -S 127.0.0.1:{port} -t G:\server\lab\sites\sc-hkai G:\server\lab\sites\sc-hkai\url_rewrite.php
// == 單站：只服務 sc-hkai（唔靠 Host 分流）
// == 規則：靜態檔直出、PHP 檔執行、內部路徑阻擋、其餘交 web\index.php 分派

// 靜態檔 Content-Type 對照（副檔名 → 類型）
function url_rewrite_content_type($file_path)
{
    $extension = pathinfo($file_path, PATHINFO_EXTENSION);
    $extension = strtolower($extension);

    $type_map = [
        "css" => "text/css; charset=utf-8",
        "js" => "application/javascript; charset=utf-8",
        "mjs" => "application/javascript; charset=utf-8",
        "json" => "application/json; charset=utf-8",
        "map" => "application/json; charset=utf-8",
        "html" => "text/html; charset=utf-8",
        "htm" => "text/html; charset=utf-8",
        "txt" => "text/plain; charset=utf-8",
        "svg" => "image/svg+xml",
        "png" => "image/png",
        "jpg" => "image/jpeg",
        "jpeg" => "image/jpeg",
        "gif" => "image/gif",
        "ico" => "image/x-icon",
        "webp" => "image/webp",
        "woff" => "font/woff",
        "woff2" => "font/woff2",
        "ttf" => "font/ttf",
        "eot" => "application/vnd.ms-fontobject",
        "pdf" => "application/pdf",
        "mp4" => "video/mp4",
    ];

    // 判定：查唔到 → 通用二進位
    if (!isset($type_map[$extension])) {
        return "application/octet-stream";
    }

    return $type_map[$extension];
}

// 出一個檔：PHP 檔入站點目錄執行（cwd 入站點，relative include 先通）；其餘原樣讀出
function url_rewrite_serve_file($file_path, $serve_dir)
{
    $extension = pathinfo($file_path, PATHINFO_EXTENSION);
    $extension = strtolower($extension);

    // 判定：PHP 檔 → 入 cwd 執行
    if (in_array($extension, ["php", "phtml", "php5", "php7"], true)) {
        chdir($serve_dir);
        require $file_path;
        return;
    }

    // 其餘＝靜態檔：出 Content-Type 後原樣讀出
    $content_type = url_rewrite_content_type($file_path);
    header("Content-Type: " . $content_type);
    readfile($file_path);
    return;
}

// 回一個純文字狀態（例：404／400）
function url_rewrite_text_status($status_code, $status_text)
{
    http_response_code($status_code);
    header("Content-Type: text/plain; charset=utf-8");
    echo $status_text;
    return;
}

// 相對路徑有冇某目錄段（頭／中／尾都算——跨層比對）
function url_rewrite_path_has_dir($request_relative, $dir_name)
{
    $request_segments = explode("/", $request_relative);

    // 逐段比對目錄名
    foreach ($request_segments as $request_segment_item) {
        // 判定：段名相同 → 中
        if ($request_segment_item === $dir_name) {
            return true;
        }
    }

    return false;
}

// 單站根：本檔所在目錄＝sc-hkai
$site_dir = __DIR__;

// 請求路徑：由 URI 去 query（/sub/page?x=1 → /sub/page）
$request_path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// 判定：解析唔到 → 當根路徑
if ($request_path === null) {
    $request_path = "/";
}

$request_path = urldecode($request_path);

// 判定：有上溯（..）→ 400（唔准跳出站點目錄）
if (strpos($request_path, "..") !== false) {
    url_rewrite_text_status(400, "400 Bad Request（路徑唔合法）");
    return;
}

// 安全防護（對照 dev .htaccess——URL 唔准入嘅內部路徑）

// 相對路徑（去頭尾斜線）＋ 段清單
$request_relative = trim($request_path, "/");
$request_segments = array_values(array_filter(explode("/", $request_relative), "strlen"));

// 段檢查①：隱藏段（.env／.git／.docs…——以點開頭一律擋）
foreach ($request_segments as $request_segment_item) {
    // 判定：以點開頭 → 403
    if (substr($request_segment_item, 0, 1) === ".") {
        url_rewrite_text_status(403, "403 Forbidden（隱藏路徑唔准直連）：" . $request_path);
        return;
    }
}

// 段檢查②：內部目錄（框架／設定／暫存——只可由 PHP 載入，唔准 URL 直連）
$blocked_dir_list = [
    "vendor",
    "sc_config",
    "sc_include",
    "sc_framework",
    "sc_api",
    "sc_routes",
    "sc_console",
    "sc_channel",
    "sc_resources",
    "_tmp",
    "_log",
    "sc_storage",
];

// 逐個目錄名跨層比對
foreach ($blocked_dir_list as $blocked_dir_item) {
    $is_blocked = url_rewrite_path_has_dir($request_relative, $blocked_dir_item);

    // 判定：中 → 403
    if ($is_blocked) {
        url_rewrite_text_status(403, "403 Forbidden（內部路徑唔准直連）：" . $request_path);
        return;
    }
}

// 段檢查③：sc_app 內 .php（動作／模板——繞過 front controller = 後門；公開資源 css／js／圖照放行）
$request_ends_php = (substr(strtolower($request_relative), -4) === ".php");
$request_in_sc_app = url_rewrite_path_has_dir($request_relative, "sc_app");

// 判定：sc_app 內 .php → 403
if ($request_in_sc_app && $request_ends_php) {
    url_rewrite_text_status(403, "403 Forbidden（sc_app 原始碼唔准直連）：" . $request_path);
    return;
}

$file_path = $site_dir . $request_path;

// 判定：係真實檔案 → 出檔
if (is_file($file_path)) {
    url_rewrite_serve_file($file_path, $site_dir);
    return;
}

// 目錄路徑（去尾斜線）：/sub/ → /sub
$dir_path = rtrim($file_path, "/");

// 判定：係目錄 → 試目錄內 index.php／index.html
if (is_dir($dir_path)) {
    $index_candidates = ["/index.php", "/index.html"];
    $index_file = "";

    // 逐個候選搵第一個存在嘅
    foreach ($index_candidates as $index_candidate_item) {
        $candidate_path = $dir_path . $index_candidate_item;

        // 判定：中 → 記低走人
        if (is_file($candidate_path)) {
            $index_file = $candidate_path;
            break;
        }
    }

    // 判定：有 index → 出檔
    if ($index_file !== "") {
        url_rewrite_serve_file($index_file, $dir_path);
        return;
    }
}

// 未中任何檔／目錄 → 交站點自己分派（對照 dev .htaccess：非實體檔一律導向 index.php）
$front_controller_file = $site_dir . "/web/index.php";

// 判定：站點有 front controller → cwd 入 web、URI 原樣交佢分派
if (is_file($front_controller_file)) {
    url_rewrite_serve_file($front_controller_file, $site_dir . "/web");
    return;
}

// 全唔中 → 404
url_rewrite_text_status(404, "404 Not Found：" . $request_path);
