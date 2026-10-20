<?php
// == sc_request/index.php：通用 request 分派器（薄層——只做「找 app → 交給 controller → require」；action 解析全在 controller） ==
// == URL：/request/{app}/{action}——app 決定載入哪個 app 的語境；controller 解析 action 並查白名單 ==

$requestPath = $this->get_request_path();
$pathParts   = explode("/", trim($requestPath, "/"));

// 缺 app → 拒絕
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
