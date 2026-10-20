<?php
// == sc_app/hkai/plugins/sc_ai_health/request/fetch_health.php：GET 心跳檢查 handler（plugin 層 action——真實狀態回報） ==
// == 回應：200 + JSON = 那一刻真實狀態（heartbeatAlive 心跳新鮮 / daemonAlive 進程待命 / stat 計數 / tasks 任務清單） ==

// 真實狀態（sc_ai_health()->health()->fetch_health()——組裝健康資料＋心跳刷新＋daemon 觸發）
$health = sc_ai_health()->health()->fetch_health();

return sc_response()->json($health);
