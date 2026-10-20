<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\request;

// == sc_ai_chats chat handle_chat_delete：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 搬到 chat 域（動作名＝chat_delete；各部分一律經 sc_ai_chats()-><域>() bridging——零域內依賴） ==

trait handle_chat_delete
{

    public function handle_chat_delete()
    {
        // 參數（JSON body——刪除目標 chat_id）
        $input_data = sc_request()->json();
        $chat_id    = (int)($input_data["chat_id"] ?? 0);

        // 判定：參數不合法 → 回錯誤
        if ($chat_id < 1) {
            return sc_response()->json(["ok" => false, "error" => "invalid chat"], 400);
        }

        // 以 chat_id 反查對話列（唔信任前端——順便攞 project_id 算下一站）
        $chat_row = sc_ai_chats()->chat()->get_chat_row($chat_id);

        // 判定：對話不存在 → 回錯誤
        if ($chat_row === null) {
            return sc_response()->json(["ok" => false, "error" => "chat not found"], 404);
        }

        // 對話歷史檔硬刪（history jsonl／meta）
        sc_ai_chats()->chat()->chat_history_delete($chat_id);

        // 每則訊息嘅檔案硬刪（現行位置＋歸檔位置）
        $message_rows = sc_ai_chats()->message()->repo_select()->get_message_list($chat_id);

        // 逐則清檔案（五檔家族——同 action delete_message 一步不差）
        foreach ($message_rows as $message_row_item) {
            $message_id = (int)$message_row_item["message_id"];

            sc_ai_chats()->poll()->delete_msg_files($chat_id, $message_id);

            // 卡檔硬刪（串流熱存——同訊息一齊清）
            sc_ai_chats()->message()->card()->delete_message_card($message_id);
        }

        // 等待列整檔刪（排隊中嘅行冇 message row——上面逐則掃唔到，喺呢度一刀清）
        sc_ai_chats()->queue()->delete_queue_file($chat_id);

        // 對話列硬刪（＋殘留 messages／message_response 列——同一事務）
        sc_ai_chats()->chat()->delete_db_chat($chat_id);

        // 前端導向目標：同專案最新的對話（沒有其他對話 → 空字串＝回專案根）
        $next_chat_sid = sc_ai_chats()->chat()->get_latest_chat_sid($chat_row["project_id"]);

        $response_json = [];
        $response_json["ok"]            = true;
        $response_json["chat_deleted"]  = true;
        $response_json["next_chat_sid"] = $next_chat_sid;

        return sc_response()->json($response_json);
    }

}
