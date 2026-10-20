<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_update;

// == sc_ai_chats message repo_update update_db_message_status：repo_update 子域動作（trait——合成入 repo_update_Class；檔＝動作） ==
// == 2026-09-27 自 message/repo_update.php 分獨立檔（one file one function） ==

trait update_db_message_status
{
    // 寫入 DB：更新訊息狀態（message_status ＋ message_update_at——事件驅動／終態／鏡像共用）
    // 同場調整對話計數（舊桶 -1、新桶 +1——同一事務；狀態無變＝唔郁）——四桶由 get_msg_count_column 決定
    public function update_db_message_status($message_id, $status)
    {
        $current_time = date("Y-m-d H:i:s");

        $connection = sc_db()->connection("sqlite");

        $connection->transaction(function ($db) use ($message_id, $status, $current_time) {
            // 舊狀態＋對話 id（計數 delta 基準——同一事務內讀）
            $old_message_row = $db->selectOne("SELECT chat_id, message_status FROM messages WHERE message_id = ?", [$message_id]);

            // 判定：查無此訊息 → 唔郁（呼叫端問題——唔喺本函數範圍）
            if ($old_message_row === null) {
                return;
            }

            $old_status      = $old_message_row["message_status"];
            $message_chat_id = (int)$old_message_row["chat_id"];

            $db->statement(
                "UPDATE messages SET message_status = ?, message_update_at = ? WHERE message_id = ?",
                [$status, $current_time, $message_id]
            );

            // 判定：狀態無變 → 計數唔使郁
            if ($old_status === $status) {
                return;
            }

            $old_count_column = $this->get_msg_count_column($old_status);
            $new_count_column = $this->get_msg_count_column($status);

            // 舊桶 -1、新桶 +1（同一個事務——計數同行）
            $db->statement("UPDATE chats SET " . $old_count_column . " = " . $old_count_column . " - 1 WHERE chat_id = ?", [$message_chat_id]);
            $db->statement("UPDATE chats SET " . $new_count_column . " = " . $new_count_column . " + 1 WHERE chat_id = ?", [$message_chat_id]);
        });
    }

    // status → 計數欄（四桶：completed／failed／cancelled 各自；其餘＝未完成＝polling_msg）
    private function get_msg_count_column($status)
    {
        // 判定：completed → completed_msg
        if ($status === "completed") {
            return "completed_msg";
        }

        // 判定：failed → failed_msg
        if ($status === "failed") {
            return "failed_msg";
        }

        // 判定：cancelled → cancelled_msg
        if ($status === "cancelled") {
            return "cancelled_msg";
        }

        return "polling_msg";
    }

}
