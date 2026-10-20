<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_update;

// == sc_ai_chats message repo_update update_db_message_timing：repo_update 子域動作（trait——合成入 repo_update_Class；檔＝動作） ==
// == 2026-09-27 自 message/repo_update.php 分獨立檔（one file one function） ==

trait update_db_message_timing
{
    // 寫入 DB：回填訊息時間與 meta（終態一次性——started_at／finished_at／elapsed_sec／meta）
    // $message_timing = ["started_at" => …, "finished_at" => …, "elapsed_sec" => …, "meta" => …]
    public function update_db_message_timing($message_id, $message_timing)
    {
        $connection = sc_db()->connection("sqlite");
        $connection->statement(
            "UPDATE messages SET message_started_at = ?, message_finished_at = ?, message_elapsed_sec = ?, message_meta = ? WHERE message_id = ?",
            [$message_timing["started_at"], $message_timing["finished_at"], $message_timing["elapsed_sec"], sc_json_encode($message_timing["meta"]), $message_id]
        );
    }

}
