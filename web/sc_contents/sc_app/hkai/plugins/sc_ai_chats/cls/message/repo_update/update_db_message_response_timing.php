<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_update;

// == sc_ai_chats message repo_update update_db_message_response_timing：repo_update 子域動作（trait——合成入 repo_update_Class；檔＝動作） ==
// == 2026-09-27 自 message/repo_update.php 分獨立檔（one file one function） ==

trait update_db_message_response_timing
{
    // 寫入 DB：回填單一 response 的檔案時間（終態一次性）
    public function update_db_message_response_timing($message_id, $response_type, $response_started_at, $response_finished_at)
    {
        $connection = sc_db()->connection("sqlite");
        $connection->statement(
            "UPDATE message_response SET response_started_at = ?, response_finished_at = ? WHERE message_id = ? AND response_type = ?",
            [$response_started_at, $response_finished_at, $message_id, $response_type]
        );
    }

}
