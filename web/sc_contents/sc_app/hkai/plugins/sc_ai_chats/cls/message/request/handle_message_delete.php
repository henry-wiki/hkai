<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\request;

// == sc_ai_chats message handle_message_delete：message 域動作（trait——掛入 message_Class；檔＝動作） ==
// == 2026-09-27 自 message/handler.php 分獨立檔（one file one function） ==

trait handle_message_delete
{

    // POST message_delete：硬刪除整則訊息（messages ＋ message_response ＋ msg 檔案）
    public function handle_message_delete()
    {
        // 參數（前端帶入——刪除目標訊息；JSON body）
        $input_data = sc_request()->json();
        $message_id = (int)($input_data["message_id"] ?? 0);

        // 判定：參數不合法 → 回錯誤
        if ($message_id < 1) {
            return sc_response()->json(["ok" => false, "error" => "invalid message"], 400);
        }

        // 以 message_id 反查 chat_id（不信任前端）
        $message_row = sc_ai_chats()->message()->repo_select()->get_message_row($message_id);
        // 判定：訊息不存在 → 回錯誤
        if ($message_row === null) {
            // 診斷 log：搵唔到目標訊息
            hkai()->runtime()->logger()->warn("message_delete not found: message_id=" . $message_id);

            return sc_response()->json(["ok" => false, "error" => "message not found"], 404);
        }
        $chat_id = (int)$message_row["chat_id"];

        // 診斷 log：定位成功
        hkai()->runtime()->logger()->info("message_delete target: chat_id=" . $chat_id . " message_id=" . $message_id);

        // 硬刪（回應中＝拒刪——delete_message 內部已把關，唔會呼喚停止）
        $remove_result = sc_ai_chats()->message()->delete_message($message_row);

        // 判定：拒刪（目標仲喺回應中）→ 409＋原因碼（前端據此顯示解釋）
        if ($remove_result["ok"] === false) {
            hkai()->runtime()->logger()->info("message_delete refused: message_id=" . $message_id . " error=" . $remove_result["error"]);

            return sc_response()->json(["ok" => false, "error" => $remove_result["error"]], 409);
        }

        // 診斷 log：完成（chat_deleted ＝ 對話有冇連帶清）
        hkai()->runtime()->logger()->info("message_delete done: chat_deleted=" . json_encode($remove_result["chat_deleted"]));

        $response_json = [];
        $response_json["ok"]           = true;
        $response_json["message_id"]   = $message_id;
        $response_json["chat_deleted"] = $remove_result["chat_deleted"];

        return sc_response()->json($response_json);
    }

}
