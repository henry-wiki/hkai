<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\action;

// == sc_ai_chats queue dequeue_prompt_message_to_poll：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 職責：推帶——抽隊頭進場（建 DB 訊息列＋整份搬去 polling）==
// == 上游：prompt_send 工序 8／handle_queue_dispatch；下游：is_queue_idle／get_queue_items／insert_queue_message_row／move_queue_item_to_polling ==

trait dequeue_prompt_message_to_poll
{
    // 推帶入口（內含兩步：①驗證 ②前移一格）；處理中＝留隊（dispatched=false）；硬失敗＝例外
    public function dequeue_prompt_message_to_poll($project_slug, $chat_sid)
    {
        // 步驟 1：推帶前驗證（對賬殘留終態＋有冇任務處理中）
        $is_queue_idle = sc_ai_chats()->queue()->is_queue_idle($project_slug, $chat_sid);

        // 判定：有任務處理中 → 留隊（回「未推帶」結果，唔算失敗）
        if ($is_queue_idle === false) {
            $shift_result = [];
            $shift_result["dispatched"]  = false;
            $shift_result["message_row"] = null;
            $shift_result["prompt_id"]   = "";
            $shift_result["poll_item"]  = [];

            return $shift_result;
        }

        // 步驟 2：前移一格（驗證通過才做）
        $queue_items = sc_ai_chats()->queue()->get_queue_items($project_slug, $chat_sid);

        // 判定：冇等待中訊息 → 冇嘢可推（正常狀態，唔算失敗）
        if (count($queue_items) < 1) {
            $shift_result = [];
            $shift_result["dispatched"]  = false;
            $shift_result["message_row"] = null;
            $shift_result["prompt_id"]   = "";
            $shift_result["poll_item"]   = [];

            return $shift_result;
        }

        $queue_item = $queue_items[0];

        // DB 訊息列（message 域 repo_update——message_id 由此出）
        $message_row = sc_ai_chats()->message()->repo_update()->insert_queue_message_row($queue_item);

        // 判定：DB 寫入失敗 → 拋錯（工序中止，訊息留隊）
        if ($message_row === null) {
            throw new \RuntimeException("dequeue insert failed: prompt_id=" . $queue_item["prompt_id"]);
        } else {
            // 專案統計（唔影響派工）
            sc_ai_projects()->project()->refresh_project_stats((int)$queue_item["project_id"]);
        }

        // 蓋章入箱（搬遷前先寫入 message_id——move 只認箱內資料）
        $queue_item["message_id"] = (int)$message_row["message_id"];

        // 進場：整份搬去 polling（只蓋 message_id）＋狀態／log／AGENTS
        sc_ai_chats()->queue()->move_queue_item_to_polling($queue_item);

        $shift_result = [];
        $shift_result["dispatched"]  = true;
        $shift_result["message_row"] = $message_row;
        $shift_result["prompt_id"]   = $queue_item["prompt_id"];
        $shift_result["poll_item"]  = $queue_item;

        return $shift_result;
    }
}
