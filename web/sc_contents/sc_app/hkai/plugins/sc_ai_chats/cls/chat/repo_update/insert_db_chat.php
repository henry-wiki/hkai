<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\repo_update;

// == sc_ai_chats chat insert_db_chat：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 chat/repo_update.php 分獨立檔（one file one function） ==

trait insert_db_chat
{

    // 寫入 DB：INSERT 對話（transaction；回讀列）——首次發送自動建立新對話用
    // 回傳：成功 = 完整 chat 列；失敗（例外/回讀不到）= null——呼叫端依此回覆錯誤
    public function insert_db_chat($project_id, $chat_data)
    {
        $chat_row     = null;
        $last_chat_id = 0;

        $connection = sc_db()->connection("sqlite");

        // 事務（刻意——INSERT 失敗要回 null 讓呼叫端回報錯誤，而非拋例外）
        try {
            $connection->transaction(function ($db) use ($project_id, $chat_data, &$last_chat_id) {
                $current_time = date("Y-m-d H:i:s");

                $chat_datapairs = [];
                $chat_datapairs["project_id"]         = $project_id;
                $chat_datapairs["chat_sid"]           = $chat_data["chat_sid"];
                // 標題硬上限 200 字（DB 邊界——PHP 只砌短標題，存入前再削一次保險）
                $chat_title = (string)$chat_data["chat_title"];
                // 判定：超過 200 字 → 截斷
                if (mb_strlen($chat_title) > 200) {
                    $chat_title = mb_substr($chat_title, 0, 200);
                }

                $chat_datapairs["chat_title"]         = $chat_title;
                $chat_datapairs["chat_tags"]          = "";
                $chat_datapairs["provider_id"]        = $chat_data["provider_id"];
                $chat_datapairs["model_id"]           = $chat_data["model_id"];
                $chat_datapairs["reasoning_effort"]   = $chat_data["reasoning_effort"];
                $chat_datapairs["chat_mode"]          = $chat_data["chat_mode"];
                $chat_datapairs["chat_message_count"] = 0;
                $chat_datapairs["chat_created_at"]    = $current_time;
                $chat_datapairs["chat_updated_at"]    = $current_time;

                list($insert_sql, $insert_bindings) = $db->insert_sql("chats", $chat_datapairs);
                $db->statement($insert_sql, $insert_bindings);
                $last_chat_id = $db->lastInsertId();
            });

            // 用 lastChatId 從 repo 取回剛寫入的 chat 列
            $chat_row = sc_ai_chats()->chat()->get_chat_row($last_chat_id);
        } catch (\Exception $exc) {
            // 寫入失敗 → 回傳 null
            return null;
        }

        return $chat_row;
    }

}
