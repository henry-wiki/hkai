<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt handle_prompt_stop：prompt 域動作（trait——掛入 prompt_Class；檔＝動作） ==
// == 2026-09-27 自 message/handler.php 分獨立檔；2026-09-27 歸 prompt 域（send 生命週期——取消） ==

trait handle_prompt_stop
{

    // POST prompt_stop：取消訊息
    // 回覆中 → FSM cancelled ＋ 精準終止該訊息的子程序
    public function handle_prompt_stop()
    {
        // 參數（JSON body——取消目標 message_id）
        $input_data = sc_request()->json();
        $message_id = (int)($input_data["message_id"] ?? 0);

        // 診斷 log：收到嘅刪除請求
        hkai__sc_runtime_io()->info("message_delete received: message_id=" . $message_id);

        // 判定：參數不合法 → 回錯誤
        if ($message_id < 1) {
            // 診斷 log：非法 message_id
            hkai__sc_runtime_io()->warn("message_delete invalid: message_id=" . $message_id);

            return sc_response()->json(["ok" => false, "error" => "invalid message"], 400);
        }

        // 以 message_id 反查 chat_id（不信任前端）
        $message_row = sc_ai_chats()->message()->repo_select()->get_message_row($message_id);

        // 判定：訊息不存在 → 回錯誤
        if ($message_row === null) {
            return sc_response()->json(["ok" => false, "error" => "message not found"], 404);
        }

        $chat_id = (int)$message_row["chat_id"];

        // 活動列收尾：最後一行留取消足跡（前端顯示 [cancelled]）
        // 必須搶喺狀態落地之前寫入——狀態一寫 cancelled，任何 poll 拍都可能會歸檔移走目錄
        $msg_log_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "log");
        if (file_exists($msg_log_file)) {
            sc_ai_chats()->poll()->log()->append_log($chat_id, $message_id, "Cancelled by user", ["event" => "CANCELLATION_REQUESTED"]);
        }

        // 狀態：CANCELLATION_REQUESTED → cancelled（fsm 對應表）
        $next_status = sc_ai_chats()->poll()->append_msg_status_by_event($chat_id, $message_id, "CANCELLATION_REQUESTED");

        // 精準終止該訊息的子程序（只殺本訊息——唔會誤殺同對話其他訊息）
        sc_ai_chats()->process()->kill_message_children($chat_id, $message_id);

        $response_json = [];
        $response_json["ok"]           = true;
        $response_json["status"]       = $next_status;
        $response_json["chat_deleted"] = false;

        return sc_response()->json($response_json);
    }

}
