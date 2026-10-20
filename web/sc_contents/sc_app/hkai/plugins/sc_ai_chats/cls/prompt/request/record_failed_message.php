<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt record_failed_message：prompt_send 私有輔助（trait——prompt_send 掛入；檔＝動作：記錄失敗訊息（RECOVERABLE——重用共用機制）） ==
// == 只 prompt_send 用 ==
// == 2026-09-27 自 prompt_send.php 分獨立檔（one file one action） ==

trait record_failed_message
{

    // 記錄失敗訊息（RECOVERABLE 錯誤——重用共用機制）
    // 回傳：已記錄 = ["existing_chat_row" => 對話列, "message_id" => 訊息 id]；未記錄（前置條件不滿足／對話取不到／寫入失敗）= null
    private function record_failed_message($input_data, $check_result, $existing_chat_row)
    {
        $project_slug = isset($input_data["project_slug"]) ? $input_data["project_slug"] : "";
        $content      = isset($input_data["content"]) ? trim($input_data["content"]) : "";
        
        // 前置條件不滿足 → 不記錄
        if (
            $project_slug === ""
            || $content === ""
        ) {
            return null;
        }
        
        // 對話列（呼叫端已解析——新對話已建立）→ 取唔到＝不記錄
        if ($existing_chat_row === null) {
            return null;
        }
        
        $project_id = (int)$existing_chat_row["project_id"];
        
        // 組裝錯誤訊息
        $error_messages = [];
        foreach ($check_result["errors"] as $field => $err) {
            $error_messages[] = $err["message"];
        }
        $error_text = implode("；", $error_messages);

        // 錯誤源（markdown——response_result 內容；顯示與檔案同一源）
        $response_source = "**無法送出**\n\n" . $error_text . "\n\n> 💡 訊息已記錄，修正以下問題後可重試";

        // 記錄：使用者問題 + 回應（response_result——錯誤源）
        $message_id = (int)sc_ai_chats()->message()->repo_update()->insert_db_message_failed($project_id, $existing_chat_row["chat_id"], $content, $response_source);

        // 寫入失敗（回 0）→ 視為未記錄
        if ($message_id === 0) {
            return null;
        }

        // 走完整流程（檔案制）：建目錄＋status（FAILED）＋response_result（錯誤源）
        $chat_id     = (int)$existing_chat_row["chat_id"];
        $message_dir = sc_ai_chats()->poll()->get_poll_dir($chat_id, $message_id);

        sc_mkdir($message_dir);

        sc_ai_chats()->poll()->append_msg_status_by_event($chat_id, $message_id, "FAILED");

        $response_result_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "response_result");

        file_put_contents($response_result_file, $response_source);

        // 收尾（回填／歷史／歸檔 archive/——同一般訊息同一支）
        sc_ai_chats()->message()->finalize_poll_job($chat_id, $message_id, "failed");

        $failed_record = [];
        $failed_record["existing_chat_row"] = $existing_chat_row;
        $failed_record["message_id"] = (int)$message_id;
        
        return $failed_record;
    }
}
