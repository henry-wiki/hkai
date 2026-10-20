<?php
// == sc_ai_chats/request/message/message_poll.php：GET 輪詢查詢狀態 handler（直接檔——completed 檔出現時解析並回寫 DB） ==

return sc_ai_chats()->poll()->request()->handle_message_poll();
