<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\history;

// == sc_ai_chats chat chat_history_update：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 chat/history.php 分獨立檔（one file one function） ==

trait chat_history_update
{

    // 終態：寫入歷史檔（① append 兩行到 {chat_sid}.jsonl ② 產生 {chat_sid}_meta.json——localdb/history/{project_slug}/）
    // 必須在 archive_msg_files 移檔之前呼叫（token 來源為 msg_stream_log）
    public function chat_history_update($chat_id, $message_id)
    {
        $message_row = sc_ai_chats()->message()->repo_select()->get_message_row($message_id);
        
        // 判定：訊息不存在 → 不動作
        if ($message_row === null) {
            return;
        }
        
        // AI 回覆（索引制 answer 段——逐段按 index 自然序拼接；舊扁平 response_result 兼容）
        $response_rows = sc_db()->connection("sqlite")->select(
            "SELECT response_type, response_content FROM message_response WHERE message_id = ? AND response_type LIKE 'response.%.answer'",
            [$message_id]
        );

        $response_content = sc_ai_chats()->message()->repo_select()->join_response_answer_contents($response_rows);

        // 判定：冇索引 answer（舊訊息／異常）→ 讀舊扁平型別
        if ($response_content === "") {
            $legacy_rows = sc_db()->connection("sqlite")->select(
                "SELECT response_content FROM message_response WHERE message_id = ? AND response_type = ? LIMIT 1",
                [$message_id, "response_result"]
            );
            $response_content = count($legacy_rows) > 0 ? $legacy_rows[0]["response_content"] : "";
        }
        
        // ① append 兩行（不讀檔、不防重）
        $history_jsonl_path = $this->get_chat_history_file_path($chat_id, ".jsonl");
        
        // 確保歷史目錄存在（{history}/{project_slug}——首次寫入前建立）
        sc_mkdir(dirname($history_jsonl_path));
        
        sc_append_jsonl($history_jsonl_path, ["role" => "user", "content" => $message_row["message_content"]]);
        sc_append_jsonl($history_jsonl_path, ["role" => "assistant", "content" => $response_content]);
        
        // 更新 msg_prompt 的 complete_token（＝主回覆的 completion_tokens；不含摘要）
        $token_sums = sc_ai_chats()->poll()->log()->get_token_sums_from_stream_log($chat_id, $message_id);
        sc_ai_chats()->prompt()->set_polling_msg_prompt_tokens($chat_id, $message_id, null, $token_sums["received"]);
        
        // ② 產生 meta（預覽 ＋ 統計——整檔重寫；檔案小）
        sc_ai_chats()->chat()->chat_history_update_meta($chat_id);
    }

}
