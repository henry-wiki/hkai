<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\request;

// == sc_ai_chats chat handle_chat_restore：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-10-04 還原（chat_status＝normal——歸檔嘅鏡像；對照 handle_chat_archive 而寫） ==

trait handle_chat_restore
{

    public function handle_chat_restore()
    {
        // 參數（JSON body——還原目標 chat_id）
        $input_data = sc_request()->json();
        $chat_id    = (int)($input_data["chat_id"] ?? 0);
        $project_slug = isset($input_data["project_slug"]) ? $input_data["project_slug"] : "";

        // 判定：參數不合法 → 回錯誤
        if ($chat_id < 1) {
            return sc_response()->json(["ok" => false, "error" => "invalid chat"], 400);
        }

        // 以 chat_id 反查對話列（唔信任前端）
        $chat_row = sc_ai_chats()->chat()->get_chat_row($chat_id);

        // 判定：對話不存在 → 回錯誤
        if ($chat_row === null) {
            return sc_response()->json(["ok" => false, "error" => "chat not found"], 404);
        }

        // 對話列還原（chats chat_status＝normal）
        sc_ai_chats()->chat()->restore_db_chat($chat_id);

        // 前端導向目標（瀏覽中嗰條被還原時用——歸檔列面 URL；URL 由後端砌）
        $next_url = "/" . $project_slug . "/archive/";

        $response_json = [];
        $response_json["ok"]            = true;
        $response_json["chat_restored"] = true;
        $response_json["next_url"]      = $next_url;

        return sc_response()->json($response_json);
    }

}
