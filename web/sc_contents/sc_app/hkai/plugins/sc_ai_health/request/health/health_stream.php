<?php

// == sc_app/hkai/plugins/sc_ai_health/request/health_stream.php：SSE 狀態串流入口（GET） ==
// == 回應：text/event-stream——每秒檢查指紋，有變才推 status event；到時自行結束（前端自動重連） ==

// 直接串流（框架 response 係緩衝式——唔可以經 sc_response）
sc_ai_health()->request()->health_stream();

exit;
