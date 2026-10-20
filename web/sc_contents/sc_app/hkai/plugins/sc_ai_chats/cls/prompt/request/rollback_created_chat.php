<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt rollback_created_chat：prompt_send 私有輔助（trait——prompt_send 掛入；檔＝動作：回滾本次請求新建嘅對話（拒絕＝不留孤兒）） ==
// == 只 prompt_send 用（外部 0 用家） ==
// == 2026-09-27 自 prompt_send.php 分獨立檔（one file one action） ==

trait rollback_created_chat
{

    // 回滾：本次請求新建嘅對話（拒絕＝不記錄——唔留孤兒空對話）
    private function rollback_created_chat($existing_chat_row, $chat_created_this_request)
    {
        // 判定：唔係本次新建 → 唔動作
        if (!$chat_created_this_request) {
            return;
        }
        
        // 判定：對話列無效 → 唔動作
        if ($existing_chat_row === null) {
            return;
        }
        
        sc_ai_chats()->chat()->delete_db_chat($existing_chat_row["chat_id"]);
    }
}
