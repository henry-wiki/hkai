<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\request;

// == sc_ai_chats poll\request handle_queue_cancel：poll request 子域動作（trait——掛入 request_Class；檔＝動作） ==
// == 2026-09-27 自 cls/poll/ 搬入（request 子域——sc_ai_chats()->poll()->request()->handle_queue_cancel()） ==

trait handle_queue_cancel
{

    //---------------------------------------------------------
    // 1. 將排隊中嗰條移出（按 uid）
    // 2. 回最新隊列 UI
    //---------------------------------------------------------
    public function handle_queue_cancel()
    {
        $input_data = sc_request()->json();

        $chat_sid  = isset($input_data["chat_sid"]) ? $input_data["chat_sid"] : "";
        $queue_item_id = isset($input_data["queue_item_id"]) ? $input_data["queue_item_id"] : "";

        // 判定：必要參數缺 → 回錯誤
        if (
            $chat_sid === ""
            || $queue_item_id === ""
        ) {
            return sc_response()->json(["ok" => false, "error" => "chat_sid and queue_item_id are required"], 400);
        }

        $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);

        // 判定：對話查無 → 回錯誤
        if ($chat_row === null) {
            return sc_response()->json(["ok" => false, "error" => "chat not found"], 404);
        }

        $chat_id = (int)$chat_row["chat_id"];

        // 移出等待列（按 uid——刪整個 pending 項目目錄）
        $project_slug = sc_ai_chats()->poll()->get_project_slug_by_chat($chat_id);

        sc_ai_chats()->queue()->remove_queue_item($project_slug, $chat_sid, $queue_item_id);

        // 回應帶最新隊列 UI（後端渲染——前端只 bind）
        $queue_list = sc_ai_chats()->prompt()->render_queue_list($chat_id);

        return sc_response()->json(["ok" => true, "html" => ["queue_list" => $queue_list]]);
    }
}
