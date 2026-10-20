<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt append_failed_message_html：prompt_send 私有輔助（trait——prompt_send 掛入；檔＝動作：RECOVERABLE 回應附加訊息 HTML（同一渲染源）） ==
// == 只 prompt_send 用 ==
// == 2026-09-27 自 prompt_send.php 分獨立檔（one file one action） ==
// == 2026-10-08 補 chat_created／chat_url（與成功路徑同一契約——唔補＝新對話已入庫但前端唔換 URL） ==

trait append_failed_message_html
{

    // RECOVERABLE 回應附加訊息 HTML（資料已入 DB = 已送出；渲染源與 message_list_fetch 相同——重載前後一致）
    private function append_failed_message_html($proc_result, $input_data, $failed_record, $chat_created)
    {
        $message_id = $failed_record["message_id"];
        $existing_chat_row   = $failed_record["existing_chat_row"];
        
        // 訊息列 + 該訊息的 error 回應列（與 fetch handler 同一讀取路徑；回應列依 message_id 分組）
        $message_row   = sc_ai_chats()->message()->repo_select()->get_message_row($message_id);
        $response_rows = sc_ai_chats()->message()->repo_select()->get_message_response_rows_by_message_id([$message_id]);
        $response_rows = array_group_by($response_rows, "message_id");
        
        // 畫面 render（統一結構：template ＋ slots——與重載時 message_list_fetch 同一渲染源）
        $message_response_rows = $response_rows[$message_id] ?? [];
        $message_render = sc_ai_chats()->message()->view()->get_chat_message_slot($message_row, $message_response_rows, false, false);
        
        $proc_result["project_slug"] = $input_data["project_slug"];
        $proc_result["chat_sid"]     = $existing_chat_row["chat_sid"];
        $proc_result["chat_id"]      = (int)$existing_chat_row["chat_id"];
        $proc_result["message_id"]   = (int)$message_id;

        // 新對話事實＋對話 URL（前端換 URL 唯一依據；同成功路徑 prompt_send 同一契約）
        $proc_result["chat_created"] = $chat_created;
        $proc_result["chat_url"]     = sc_ai_chats()->chat()->build_chat_url($input_data["project_slug"], "normal", $existing_chat_row["chat_sid"]);

        $proc_result["html"]["new_message"] = $message_render;
        
        return $proc_result;
    }
}
