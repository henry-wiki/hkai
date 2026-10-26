<?php
// == sc_app/hkai/plugins/sc_ai_settings/request/server/server_status.php：server 狀態查詢 handler（bridge 快查） ==
// == 回應：200 + JSON（is_alive／method／checked_at／server_status／heartbeat 原始資料） ==

// bridge 狀態（讀本機心跳檔＋pid 核實；檢查邏輯在 hkai()->process()->bridge_watch()）
$server_status_result = hkai()->process()->server()->get_server_status();

return sc_response()->json($server_status_result);
