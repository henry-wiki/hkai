<?php

namespace sc\app\hkai\plugins\sc_ai_chats\process;

// == sc_ai_chats process 域 internal 短名（_.internal.func.php·子域版）：同 namespace 直接叫（process()——免 use function） ==
// == 委派全局 bridging（解析源單一——class 引用仍只喺插件 cls/_.func.php） ==
// == 載入：檔尾 .func.php——框架開機遞迴掃描自動載入（零註冊）；短名住子域 namespace＝同其它 plugin 隔離、零撞名 ==
// == 取名規則：本域單元用裸名（process——域本體含全部動作） ==

/**
 * process 單例（本域短名——例：process()->kill_message_children($chat_id, $message_id)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_chats\process\process_Class
 */
function process()
{
    return sc_ai_chats()->process();
}
