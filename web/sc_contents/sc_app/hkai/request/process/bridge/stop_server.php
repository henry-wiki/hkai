<?php

// == sc_app/hkai/request/process/bridge/stop_server.php：POST 停止 python server（app 層 action）
// == 輸入：無；回應：{ok:true} 或 {ok:false,error}（未殺清 → 500）
// == 下游：hkai()->process()->server()->stop_server()（本檔只做 HTTP status 對映）

$server_result = hkai()->process()->server()->stop_server();

// 判定：未殺清 → 500
if ($server_result["ok"] === false) {
    return sc_response()->json(["ok" => false, "error" => $server_result["error"]], 500);
}

return sc_response()->json(["ok" => true]);
