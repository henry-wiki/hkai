<?php

// == plugins/sc_stream/request/prompt_stream.php：草稿串流 handler（app 層 action——SSE） ==
// == 回應：text/event-stream；主迴圈在 cls/sse/sse_prompt ==

sc_stream()->sse_prompt()->run_prompt_stream();

exit;

