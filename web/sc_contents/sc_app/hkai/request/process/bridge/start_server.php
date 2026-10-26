<?php

// == sc_app/hkai/request/process/bridge/start_server.php：POST 啟動 python server（app 層 action）
// == 輸入：{host?, port?}；回應：{ok,host,port} 或 {ok:false,error}
// == 下游：hkai()->process()->server()->start_server()（本檔只做 HTTP status 對映）

$request_data = sc_request()->json();

$server_result = hkai()->process()->server()->start_server($request_data["host"] ?? "", $request_data["port"] ?? "");

// 判定：失敗 → 按 error_kind 對映 HTTP status（no_port 409／failed 500）
if ($server_result["ok"] === false) {
    $error_status_map = ["no_port" => 409, "failed" => 500];
    $error_status     = $error_status_map[$server_result["error_kind"]] ?? 500;

    return sc_response()->json(["ok" => false, "error" => $server_result["error"]], $error_status);
}

return sc_response()->json($server_result);
