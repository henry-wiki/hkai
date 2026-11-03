<?php
// == sc_request/index.php：通用 request 分派器（薄層——只做「找 app → 交給 controller → require」；action 解析全在 controller） ==
// == URL：/request/{app}/{action}——app 決定載入哪個 app 的語境；controller 解析 action 並查白名單 ==

$requestPath = $this->get_request_path();
$pathParts   = explode("/", trim($requestPath, "/"));

// TEMP TRACE（追溯 %SystemDrive%——查完移除）：request 開始／結束各一筆
$sd_trace_write = function ($trace_tag) {
    $services_dir = sc_normalize_path(sc_get_config("services"));
    $trace_file   = dirname($services_dir) . "/_tmp/sd_trace.log";
    $trace_folder = $services_dir . "/%SystemDrive%";
    $system_drive = getenv("SYSTEMDRIVE");
    $folder_state = "no";

    // 判定：資料夾已存在 → 記 YES
    if (is_dir($trace_folder) === true) {
        $folder_state = "YES";
    }

    $trace_line = date("Y-m-d H:i:s") . " | " . $trace_tag
        . " | pid=" . getmypid()
        . " | cwd=" . getcwd()
        . " | SYSTEMDRIVE=" . ($system_drive === false ? "<missing>" : $system_drive)
        . " | folder=" . $folder_state . "
";

    @file_put_contents($trace_file, $trace_line, FILE_APPEND | LOCK_EX);
};

$sd_trace_write("php_req_begin:" . $requestPath);
register_shutdown_function(function () use ($sd_trace_write) {
    $sd_trace_write("php_req_end");
});

$appName = isset($pathParts[1]) ? $pathParts[1] : "";
if ($appName === "") {
    return sc_response()->json(["error" => "invalid request path"], 404);
}

// action key：第 3 段起併回（支援斜線分隔 action——如 settings/get_server_settings）
$requestKey = isset($pathParts[2]) ? implode("/", array_slice($pathParts, 2)) : "";

$appDir = SC_DIR_APP . "/" . $appName;

// app 目錄不存在 → 拒絕
if (!is_dir($appDir)) {
    return sc_response()->json(["error" => "unknown app: " . $appName], 404);
}

// 建 app 語境 controller 並交給它分派（解析段位 + 載入語境 + 查白名單——controller 為中心）
$appController = sc_controller([
    "route_path"   => $appDir,
    "request_path" => $requestPath,
    "route_data"   => [],
    "group"        => "app",
]);
$targetFile = $appController->dispatch_request($requestKey);

// 未登記的 action → 拒絕
if ($targetFile === null) {
    return sc_response()->json(["error" => "action not registered"], 404);
}

// 執行目標 FILE（return 結果由外層 dispatch prepare_response 統一轉 Response）
return require $targetFile;
