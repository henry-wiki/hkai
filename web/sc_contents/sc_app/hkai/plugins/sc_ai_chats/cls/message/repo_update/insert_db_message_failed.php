<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_update;

// == sc_ai_chats message repo_update insert_db_message_failed：repo_update 子域動作（trait——合成入 repo_update_Class；檔＝動作） ==
// == 2026-09-27 自 message/repo_update.php 分獨立檔（one file one function） ==

trait insert_db_message_failed
{
    // 寫入 DB：INSERT 失敗訊息（status = "failed"；response＝response_result——錯誤源 markdown）
    // 回傳：成功 = message_id；失敗 = 0
    public function insert_db_message_failed($project_id, $chat_id, $content, $response_content)
    {
        $last_message_id = 0;

        $connection = sc_db()->connection("sqlite");

        try {
            $connection->transaction(function ($db) use ($project_id, $chat_id, $content, $response_content, &$last_message_id) {
                $current_time = date("Y-m-d H:i:s");

                $message_datapairs = [
                    "chat_id" => $chat_id,
                    "project_id" => $project_id,
                    "message_content" => $content,
                    "message_status" => "failed",
                    "message_chat_mode" => "",
                    "message_provider_id" => "",
                    "message_model_id" => "",
                    "message_created_at" => $current_time,
                    "message_update_at" => $current_time,
                ];

                list($insertSql, $insertBindings) = $db->insert_sql("messages", $message_datapairs);
                $db->statement($insertSql, $insertBindings);
                $last_message_id = $db->lastInsertId();

                // → 寫入 response_result（錯誤源——同顯示同一內容）
                $response_datapairs = [
                    "project_id" => $project_id,
                    "chat_id" => $chat_id,
                    "message_id" => $last_message_id,
                    "response_type" => "response_result",
                    "response_content" => $response_content,
                    "response_created_at" => $current_time,
                    "response_updated_at" => $current_time,
                ];

                list($respSql, $respBindings) = $db->insert_sql("message_response", $response_datapairs);
                $db->statement($respSql, $respBindings);

                // → 更新 count（新訊息＝failed → failed_msg +1）
                $count_msg = "SELECT COUNT(*) FROM messages WHERE chat_id = {$chat_id}";
                $db->statement(
                    "UPDATE chats SET chat_message_count = (".$count_msg."), failed_msg = failed_msg + 1, chat_updated_at = ? WHERE chat_id = ?",
                    [$current_time, $chat_id]
                );
            });
        } catch (\Exception $exc) {
            return 0;
        }

        return $last_message_id;
    }

}
