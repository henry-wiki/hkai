<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\history;

// == sc_ai_chats chat chat_history_delete：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 chat/history.php 分獨立檔（one file one function） ==

trait chat_history_delete
{
    // 硬刪除該對話的歷史檔（① {chat_sid}.jsonl ② {chat_sid}_meta.json——命名規則同 chat_history_update）
    public function chat_history_delete($chat_id)
    {
        $history_paths   = [];
        $history_paths[] = $this->get_chat_history_file_path($chat_id, ".jsonl");
        $history_paths[] = $this->get_chat_history_file_path($chat_id, "_meta.json");

        // 逐檔刪除（無歸檔位置——history 目錄為唯一存放處）
        foreach ($history_paths as $file_path_item) {
            // 判定：檔案存在 → 刪除
            if (file_exists($file_path_item)) {
                unlink($file_path_item);
            }
        }
    }

}
