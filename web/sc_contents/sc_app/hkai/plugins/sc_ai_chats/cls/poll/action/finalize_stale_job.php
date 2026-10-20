<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\action;

// == sc_ai_chats poll finalize_stale_job：poll 域動作（trait——掛入 poll_Class；檔＝動作） ==
// == 2026-09-29 新設（health 動作掛點——收尾孤兒 job：狀態檔已終態，但前端 poll 冇再踩到嗰個對話 → 冇人收尾） ==

trait finalize_stale_job
{

    //---------------------------------------------------------
    // 1. 攞跨對話非終態訊息（DB——未收尾候選）
    // 2. 逐條讀狀態檔尾行（SSOT——同 get_poll_status）
    // 3. 尾行係終態 ＋ 狀態檔停滯超過門檻 → 交 finalize_poll_job 收尾
    //---------------------------------------------------------
    public function finalize_stale_job()
    {
        $stale_message_rows = sc_ai_chats()->message()->repo_select()->get_non_terminal_message_rows();

        foreach ($stale_message_rows as $stale_message_row_item) {
            $chat_id    = (int)$stale_message_row_item["chat_id"];
            $message_id = (int)$stale_message_row_item["message_id"];

            // status 與 poll 同源（尾行＝最新；檔唔存在＝空）
            $status = $this->file()->get_poll_status($chat_id, $message_id);

            // 判定：唔係終態 → 未完成（正常鏈仲喺度處理）→ 唔郁
            if (!sc_ai_chats()->fsm()->is_terminal_status($status)) {
                continue;
            }

            // 判定：狀態檔仍然新鮮（啱啱完成——前端 poll 仲會收到）→ 唔搶
            if ($this->is_poll_status_fresh($chat_id, $message_id)) {
                continue;
            }

            // 終態 ＋ 停滯夠久 → 單條收尾（回填→歷史→status→歸檔——次序喺 finalize_poll_job 寫死）
            sc_ai_chats()->message()->finalize_poll_job($chat_id, $message_id, $status);
        }
    }

    // 狀態檔是否仍然新鮮（未過停滯門檻——剛完成者留返前端 poll 收尾）
    private function is_poll_status_fresh($chat_id, $message_id)
    {
        $status_file = $this->get_poll_file($chat_id, $message_id, "status");

        // 判定：檔唔存在 → 唔當新鮮（無檔可辨——交返上層照常處理）
        if (!file_exists($status_file)) {
            return false;
        }

        $status_file_age_seconds = time() - filemtime($status_file);

        return $status_file_age_seconds < $this->stale_job_seconds;
    }

}
