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

        // 每則訊息嘅檔案硬刪（現行位置＋歸檔位置；目錄名＝get_poll_dir_name）
        $message_rows = sc_ai_chats()->message()->repo_select()->get_message_list($chat_id);

        $project_slug = sc_ai_chats()->poll()->get_project_slug_by_chat($chat_id);

        // 判定：專案 slug 空（專案已刪）→ 拋錯（唔好半刪）
        if ($project_slug === "") {
            throw new \RuntimeException("chat_delete missing project_slug: chat_id=" . $chat_id);
        }

        // 逐則清檔案（五檔家族——同 action delete_message 一步不差）
        foreach ($message_rows as $message_row_item) {
            $message_id = (int)$message_row_item["message_id"];

            sc_ai_chats()->poll()->delete_poll_files($project_slug, $message_id);
            sc_ai_chats()->archive()->delete_archive_files($project_slug, $message_id);
        }

        // 等待列整檔刪（排隊中嘅行冇 message row——上面逐則掃唔到，喺呢度一刀清）
        sc_ai_chats()->queue()->delete_queue_chat($project_slug, (string)$chat_row["chat_sid"]);

        // 永久附件庫整批硬刪（<base>/<slug>/<sid>——本對話所有訊息附件）
        $chat_attachments_dir = sc_get_config("hkai.messages_attachments_dir") . "/" . $project_slug . "/" . (string)$chat_row["chat_sid"];
        sc_rrmdir($chat_attachments_dir);

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
