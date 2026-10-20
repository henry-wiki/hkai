<?php
// == sc_ai_chats/request/queue/queue_cancel.php：取消等待列項目 handler（實作歸 poll 域——收尾鏈） ==

return sc_ai_chats()->poll()->request()->handle_queue_cancel();
