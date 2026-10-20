<?php
// == sc_ai_chats/request/queue/queue_dispatch.php：派工（收尾＋隊頭進場）handler（實作歸 poll 域——收尾鏈） ==

return sc_ai_chats()->poll()->request()->handle_queue_dispatch();
