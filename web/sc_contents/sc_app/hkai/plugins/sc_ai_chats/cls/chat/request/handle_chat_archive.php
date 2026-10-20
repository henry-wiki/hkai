<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\request;

// == sc_ai_chats chat handle_chat_archive：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-28 歸檔（chat_status＝archived——唔碰任何訊息／檔案；對照 handle_chat_delete 而寫） ==

trait handle_chat_archive
{

    public function handle_chat_archive()
    {
        // 參數（JSON body——歸檔目標 chat_id）
        $input_data = sc_request()->json();
        $chat_id    = (int)($input_data["chat_id"] ?? 0);

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

        // 對話列歸檔（chats chat_status＝archived）
        sc_ai_chats()->chat()->archive_db_chat($chat_id);

        // 前端導向目標：同專案最新嘅未歸檔對話（冇其他 → 空字串＝回專案根）
        $next_chat_sid = sc_ai_chats()->chat()->get_latest_chat_sid($chat_row["project_id"]);

        $response_json = [];
        $response_json["ok"]            = true;
        $response_json["chat_archived"] = true;
        $response_json["next_chat_sid"] = $next_chat_sid;

        return sc_response()->json($response_json);
    }

}
