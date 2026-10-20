<?php
// == sc_app/hkai/plugins/sc_ai_health/request/fetch_bridge_check.php：bridge 檢查 handler（獨立鏈接——統一快速檢查端點） ==
// == 回應：200 + JSON（is_alive／method／checked_at／server_status／heartbeat 原始資料） ==

// bridge 檢查（method 依 plugin config——local-file 讀心跳檔／http 探測；檢查邏輯在 sc_python_bridge_check）
$bridge_check = hkai__python_bridge_check()->check_is_python_bridge_running();

return sc_response()->json($bridge_check);
