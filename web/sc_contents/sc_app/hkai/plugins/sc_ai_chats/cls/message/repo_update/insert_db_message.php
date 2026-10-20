<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_update;

// == sc_ai_chats message repo_update insert_db_message：repo_update 子域動作（trait——合成入 repo_update_Class；檔＝動作） ==
// == 2026-09-27 自 message/repo_update.php 分獨立檔（one file one function） ==

trait insert_db_message
{

    // 寫入 DB：INSERT 訊息 + 重算 count 為同一動作——包事務，中途失敗整體 rollback
    // $message_model_info = ["chat_mode" => …, "provider_id" => …, "model_id" => …]
    // 回傳：成功 = 完整 message 列；失敗（例外/回讀不到）= null——呼叫端依此回覆錯誤
    public function insert_db_message($project_id, $chat_id, $content, $message_model_info)
    {
        $message_row = null;

        $last_message_id = 0;

        $connection = sc_db()->connection("sqlite");

        // 盲捕（刻意——INSERT 失敗要回傳 null 讓呼叫端回覆錯誤，不向外拋 500）
        try {
            $connection->transaction(function ($db) use ($project_id, $chat_id, $content, $message_model_info, &$last_message_id) {
                $current_time = date("Y-m-d H:i:s");

                $message_datapairs = [
                    "chat_id" => $chat_id,
                    "project_id" => $project_id,
                    "message_content" => $content,
                    "message_status" => "queued",
                    "message_chat_mode" => $message_model_info["chat_mode"],
                    "message_provider_id" => $message_model_info["provider_id"],
                    "message_model_id" => $message_model_info["model_id"],
                    "message_created_at" => $current_time,
                    "message_update_at" => $current_time,
                ];

                // → 單一 INSERT 資料列
                list($insertSql, $insertBindings) = $db->insert_sql("messages", $message_datapairs);
                $db->statement($insertSql, $insertBindings);
                $last_message_id = $db->lastInsertId();

                // → 更新 count（同 SQL 單一 statement——子查詢即時 COUNT；新訊息＝queued → polling_msg +1；附帶記低本次模型——對話列顯示用）
                $count_msg = "SELECT COUNT(*) FROM messages WHERE chat_id = {$chat_id}";
                $db->statement(
                    "UPDATE chats SET chat_message_count = (".$count_msg."), polling_msg = polling_msg + 1, chat_updated_at = ?, provider_id = ?, model_id = ? WHERE chat_id = ?",
                    [$current_time, $message_model_info["provider_id"], $message_model_info["model_id"], $chat_id]
                );

            });

            // 用 lastMessageId 從 repo 取回剛寫入的 message 列
            $message_row = sc_ai_chats()->message()->repo_select()->get_message_row($last_message_id);
        } catch (\Exception $exc) {
            // 寫入失敗 → 回傳 null
            return null;
        }

        return $message_row;
    }

}
