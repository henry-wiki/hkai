<?php

// == plugins/sc_stream/request/state_stream.php：狀態串流 handler（app 層 action——SSE） ==
// == 回應：text/event-stream；主迴圈在 cls/sse/sse_state ==

sc_stream()->sse_state()->run_state_stream();

exit;

