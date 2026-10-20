<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_select;

// == sc_ai_chats message repo_select get_message_response_brief_rows：repo_select 子域動作（trait——合成入 repo_select_Class；檔＝動作：回應列簡版單條讀取） ==

trait get_message_response_brief_rows
{
    // 讀取單一訊息嘅回應列簡版（3 欄——message_id／poll_file／response_status；舊制行冇 poll_file＝唔入）
    public function get_message_response_brief_rows($message_id)
    {
        $pdo = sc_db()->getPdo();

        $selectStmt = $pdo->prepare(
            "SELECT message_id, poll_file, response_status FROM message_response WHERE message_id = ? AND poll_file <> ''"
        );
        $selectStmt->execute([$message_id]);

        return $selectStmt->fetchAll(\PDO::FETCH_ASSOC);
    }

}
