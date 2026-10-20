<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\action;

// == sc_ai_chats message delete_message：message 域動作（trait——掛入 message_Class；檔＝動作） ==
// == 2026-09-27 自 message/action.php 分獨立檔（one file one function） ==

trait delete_message
{

    //---------------------------------------------------------
    // 1. 刪 DB（回應＋訊息＋重算數）
    // 2. 刪訊息檔案
    // 3. 對話清空＝連對話一齊刪
    //---------------------------------------------------------
    // 回傳：["chat_deleted" => bool]（true ＝ 對話已連帶清掉——前端導向專案根）
    public function delete_message($message_row)
    {
        $chat_id      = (int)$message_row["chat_id"];
        $message_id   = (int)$message_row["message_id"];

        // 診斷 log：開始
        hkai__sc_runtime_io()->info("delete_message start: chat_id=" . $chat_id . " message_id=" . $message_id);

        // DB 硬刪（message_response 列＋messages 列＋重算 chat count——repo_update 單一入口）
        sc_ai_chats()->message()->repo_update()->delete_db_message($message_id, $chat_id);

        // 卡檔硬刪（串流熱存——同訊息一齊清）
        sc_ai_chats()->message()->card()->delete_message_card($message_id);

        // 診斷 log：DB 列完成
        hkai__sc_runtime_io()->info("delete_message db rows removed");

        // msg 檔案硬刪（現行位置 ＋ 歸檔位置）
        sc_ai_chats()->poll()->delete_msg_files($chat_id, $message_id);

        // 診斷 log：msg 檔案完成
        hkai__sc_runtime_io()->info("delete_message msg files removed");

        $remove_result = [];
        $remove_result["chat_deleted"] = false;

        // 判定：對話已無訊息（剛刪掉最後一則）→ 連對話一起清掉（空對話沒有存在意義——chat 只在首次送出時建立）
        if (sc_ai_chats()->chat()->is_chat_empty($chat_id)) {
            // 等待列整檔刪（排隊中嘅行——對話將亡，等待列即無意義）
            sc_ai_chats()->queue()->delete_queue_file($chat_id);

            // 對話歷史檔硬刪（history jsonl／meta）
            sc_ai_chats()->chat()->chat_history_delete($chat_id);

            // chats 列硬刪（＋殘留關聯列保險清）
            sc_ai_chats()->chat()->delete_db_chat($chat_id);

            // 診斷 log：對話已空 → 連帶清咗
            hkai__sc_runtime_io()->info("delete_message chat emptied -> chat removed: chat_id=" . $chat_id);

            // 對話已清 → 前端無痕導向專案根（/{project_slug}/——前端用自身 project_slug SSOT 砌網址，後端唔回導向目標）
            $remove_result["chat_deleted"] = true;
        }

        return $remove_result;
    }

}
