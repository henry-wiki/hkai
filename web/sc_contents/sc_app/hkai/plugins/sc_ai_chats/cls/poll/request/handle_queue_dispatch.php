<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\request;

// == sc_ai_chats poll\request handle_queue_dispatch：poll request 子域動作（trait——掛入 request_Class；檔＝動作） ==
// == 2026-09-27 自 cls/poll/ 搬入（request 子域——sc_ai_chats()->poll()->request()->handle_queue_dispatch()） ==

trait handle_queue_dispatch
{

    //---------------------------------------------------------
    // 1. 抽排隊第一條入場
    // 2. 回隊列 UI＋新訊息氣泡
    //---------------------------------------------------------
    public function handle_queue_dispatch()
    {
        $input_data = sc_request()->json();
        $chat_sid   = isset($input_data["chat_sid"]) ? $input_data["chat_sid"] : "";

        // 判定：無 chat_sid → 報錯
        if ($chat_sid === "") {
            return sc_response()->json(["ok" => false, "error" => "chat_sid is required"], 400);
        }

        $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);

        // 判定：對話查無 → 回錯誤
        if ($chat_row === null) {
            return sc_response()->json(["ok" => false, "error" => "chat not found"], 404);
        }

        $chat_id = (int)$chat_row["chat_id"];

        // ① 進場：對話空閒即隊頭派工入對話
        $shift_result = sc_ai_chats()->queue()->shift_queue_message($chat_id);

        $response_data = [];
        $response_data["ok"]   = true;
        $response_data["html"] = ["queue_list" => sc_ai_chats()->prompt()->render_queue_list($chat_id)];

        // 判定：有訊息進場 → 帶新訊息氣泡（前端 insert——同送出回應同一渲染源）
        if ($shift_result !== null) {
            $message_row = $shift_result["message_row"];

            $response_data["chat_id"]    = (int)$message_row["chat_id"];
            $response_data["message_id"] = (int)$message_row["message_id"];
            $response_data["html"]["new_message"] = sc_ai_chats()->message()->view()->get_chat_message_slot($message_row, [], true);
        }

        return sc_response()->json($response_data);
    }
}
