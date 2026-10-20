<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\request;

// == sc_ai_chats poll\request handle_queue_clear：poll request 子域動作（trait——掛入 request_Class；檔＝動作） ==
// == 2026-09-30 跟 reference3 上工具列「清除排隊」——清成個等待列（用已存在動作 delete_queue_file） ==

trait handle_queue_clear
{

    //---------------------------------------------------------
    // 1. 清空等待列（成個排隊檔）
    // 2. 回最新隊列 UI
    //---------------------------------------------------------
    public function handle_queue_clear()
    {
        $input_data = sc_request()->json();

        $chat_sid = isset($input_data["chat_sid"]) ? $input_data["chat_sid"] : "";

        // 判定：必要參數缺 → 回錯誤
        if ($chat_sid === "") {
            return sc_response()->json(["ok" => false, "error" => "chat_sid is required"], 400);
        }

        $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);

        // 判定：對話查無 → 回錯誤
        if ($chat_row === null) {
            return sc_response()->json(["ok" => false, "error" => "chat not found"], 404);
        }

        $chat_id = (int)$chat_row["chat_id"];

        // 清空等待列（成個排隊檔刪走——檔唔存在＝檔內已判，零動作）
        sc_ai_chats()->queue()->delete_queue_file($chat_id);

        // 回應帶最新隊列 UI（後端渲染——前端只 bind）
        $queue_list = sc_ai_chats()->prompt()->render_queue_list($chat_id);

        return sc_response()->json(["ok" => true, "html" => ["queue_list" => $queue_list]]);
    }
}
