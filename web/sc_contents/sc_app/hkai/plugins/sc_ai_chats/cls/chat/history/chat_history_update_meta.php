<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\history;

// == sc_ai_chats chat chat_history_update_meta：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 chat/history.php 分獨立檔（one file one function） ==

trait chat_history_update_meta
{
    // 產生 {chat_sid}_meta.json（預覽＋統計；content 以「字元」截斷、meta.tokens 為該則 tokens）
    private function chat_history_update_meta($chat_id)
    {
        $connection = sc_db()->connection("sqlite");
        $message_rows = $connection->select(
            "SELECT message_id, message_content FROM messages WHERE chat_id = ? ORDER BY message_id ASC",
            [$chat_id]
        );
        
        $meta_messages = [];
        $total_tokens  = 0;
        
        // 逐則訊息組出 meta 預覽（user）
        foreach ($message_rows as $message_row_item) {
            // 取 prompt 檔（JSON）：input_token ＝ 問題本身、complete_token ＝ 回覆本身
            // 為什麼不用 message_meta.tokens_sent：那是「整輪請求」（含系統提示＋repo map＋歷史），不是單則
            $prompt_file_path = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_row_item["message_id"], "prompt");
            
            // 判定：現行位置不存在（舊訊息已被歸檔）→ 改讀歸檔位置（archive/{project_slug}/{message_id}/；歸檔側路徑單一來源）
            if (!is_file($prompt_file_path)) {
                $archive_dir      = sc_ai_chats()->poll()->get_archived_poll_dir($chat_id, $message_row_item["message_id"]);
                $prompt_file_path = $archive_dir . "/" . basename($prompt_file_path);
            }
            
            $prompt_data = sc_ai_chats()->prompt()->parse_polling_prompt_file($prompt_file_path);
            
            $user_tokens = isset($prompt_data["input_token"]) ? (int)$prompt_data["input_token"] : 0;
            $total_tokens += $user_tokens;
            
            $meta_messages[] = [
                "role"    => "user",
                "content" => sc_truncate($message_row_item["message_content"], 100),
                "meta"    => ["tokens" => $user_tokens],
            ];
            
            // AI 回覆（索引制 answer 段拼接；舊扁平 response_result 兼容）
            $response_rows = $connection->select(
                "SELECT response_type, response_content FROM message_response WHERE message_id = ? AND response_type LIKE 'response.%.answer'",
                [$message_row_item["message_id"]]
            );

            $response_content = sc_ai_chats()->message()->repo_select()->join_response_answer_contents($response_rows);

            // 判定：冇索引 answer（舊訊息／異常）→ 讀舊扁平型別
            if ($response_content === "") {
                $legacy_rows = $connection->select(
                    "SELECT response_content FROM message_response WHERE message_id = ? AND response_type = ? LIMIT 1",
                    [$message_row_item["message_id"], "response_result"]
                );
                $response_content = count($legacy_rows) > 0 ? $legacy_rows[0]["response_content"] : "";
            }
            
            // 判定：無回覆內容 → 不加入（維持 user/assistant 對應）
            if (trim($response_content) === "") {
                continue;
            }
            
            $assistant_tokens = isset($prompt_data["complete_token"]) ? $prompt_data["complete_token"] : 0;
            $total_tokens += $assistant_tokens;
            
            $meta_messages[] = [
                "role"    => "assistant",
                "content" => sc_truncate($response_content, 100),
                "meta"    => ["tokens" => $assistant_tokens],
            ];
        }
        
        $meta_data = [];
        $meta_data["chat_id"]        = (int)$chat_id;
        $meta_data["updated_at"]     = date("Y-m-d H:i:s");
        $meta_data["total_messages"] = count($meta_messages);
        $meta_data["total_tokens"]   = $total_tokens;
        $meta_data["messages"]       = $meta_messages;
        
        $meta_path = $this->get_chat_history_file_path($chat_id, "_meta.json");
        
        sc_write_json($meta_path, $meta_data);
    }

}
