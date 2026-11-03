<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_update;

// == sc_ai_chats message repo_update reset_db_message_attempt：repo_update 子域動作（trait——合成入 repo_update_Class；檔＝動作） ==
// == 職責：恢復前重設本次回覆時間基準（started_at＝現在、清 finished_at／elapsed_sec） ==
// == 上游：poll reset_partial_response（先清洗後派工）；下游：messages 表 ==

trait reset_db_message_attempt
{
    // 重設單條訊息嘅「本次回覆」時間基準（創建時間保留——只重置嘗試起點）
    public function reset_db_message_attempt($message_id)
    {
        $current_time = date("Y-m-d H:i:s");

        $connection = sc_db()->connection("sqlite");

        $connection->statement(
            "UPDATE messages SET message_started_at = ?, message_finished_at = NULL, message_elapsed_sec = 0 WHERE message_id = ?",
            [$current_time, $message_id]
        );
    }
}
