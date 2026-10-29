<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\action;

// == sc_ai_chats poll finalize_terminal_jobs：全輪詢區終態收尾（trait——掛入 poll_Class；檔＝動作） ==
// == 上游：health_stream（每 N tick——回覆改由 SSE 推後，冇 message_poll 收尾） ==
// == 下游：archive()->finalize_poll_job（回填→歷史→status→歸檔） ==

trait finalize_terminal_jobs
{
    // 掃全部輪詢目錄：status 終態 → 逐條收尾（回傳處理數量）
    public function finalize_terminal_jobs()
    {
        $finalized_count = 0;

        $poll_dirs = sc_ai_chats()->poll()->file()->get_poll_dirs(null);

        foreach ($poll_dirs as $_poll_dir_item) {
            $meta_file = $_poll_dir_item . "/meta.json";

            // 判定：冇 meta.json → 跳過
            if (is_file($meta_file) === false) {
                continue;
            }

            $meta_text = @file_get_contents($meta_file);

            // 判定：讀唔到 → 跳過
            if ($meta_text === false) {
                continue;
            }

            $meta_data = json_decode($meta_text, true);

            // 判定：內容壞 → 跳過
            if (is_array($meta_data) === false) {
                continue;
            }

            $_chat_id    = (int)($meta_data["chat_id"] ?? 0);
            $_message_id = (int)($meta_data["message_id"] ?? 0);

            // 判定：缺 id → 跳過（唔可以收尾）
            if ($_chat_id < 1 || $_message_id < 1) {
                continue;
            }

            // 狀態以 msg_status.txt 最後一行為準（meta.json 的 status 由 PHP 回寫，worker 完成時未必同步）
            $_status = (string)sc_ai_chats()->poll()->file()->get_poll_status($_chat_id, $_message_id);

            // 判定：唔係終態 → 跳過
            if (sc_ai_chats()->fsm()->is_terminal_status($_status) === false) {
                continue;
            }

            sc_ai_chats()->archive()->finalize_poll_job($_chat_id, $_message_id, $_status);

            $finalized_count = $finalized_count + 1;
        }

        return $finalized_count;
    }
}
