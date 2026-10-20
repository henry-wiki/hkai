<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_update;

// == sc_ai_chats message repo_update close_db_message_response_rows：repo_update 子域動作（trait——合成入 repo_update_Class；檔＝動作） ==
// == 2026-10-06 新設：終態收邊保險——仍 poll 嘅段行按訊息終態收（completed／aborted；同 daemon complete_all／abort_all 對稱） ==

trait close_db_message_response_rows
{
    // 未終態（poll）段行收邊（按訊息終態：completed → completed；其餘（failed／cancelled）→ aborted）
    public function close_db_message_response_rows($message_id, $message_status)
    {
        // 判定：訊息 completed → 收 completed；其餘（failed／cancelled）→ 收 aborted
        if ($message_status === "completed") {
            $closed_response_status = "completed";
        } else {
            $closed_response_status = "aborted";
        }

        $connection = sc_db()->connection("sqlite");
        $connection->statement(
            "UPDATE message_response SET response_status = ? WHERE message_id = ? AND response_status = 'poll'",
            [$closed_response_status, $message_id]
        );
    }

}
