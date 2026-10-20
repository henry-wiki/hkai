<?php
// == sc_ai_chats/request/queue/queue_clear.php：清空等待列 handler（實作歸 poll 域——收尾鏈） ==

return sc_ai_chats()->poll()->request()->handle_queue_clear();
