<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt handle_prompt_stop：prompt 域動作（trait——掛入 prompt_Class；檔＝動作） ==
// == 2026-09-27 自 message/handler.php 分獨立檔；2026-09-27 歸 prompt 域（send 生命週期——取消） ==

trait handle_prompt_stop
{

    // POST prompt_stop：取消訊息（共用入口——route 呼叫＝無參數；內部呼叫＝直接傳 message_id）
    // 回覆中 → FSM cancelled ＋ 精準終止該訊息的子程序
    public function handle_prompt_stop($message_id = null)
    {
        // 呼叫來源（true＝route；false＝內部直接呼叫——回傳值唔同）
        $is_request_call = ($message_id === null);

        // 判定：route 呼叫（冇傳參）→ 由 JSON body 取 message_id
        if ($message_id === null) {
            $input_data = sc_request()->json();
            $message_id = (int)($input_data["message_id"] ?? 0);
        } else {
            $message_id = (int)$message_id;
        }

        // 診斷 log：收到嘅停止請求
        hkai()->runtime()->logger()->info("prompt_stop received: message_id=" . $message_id);

        // 判定：參數不合法 → 回錯誤
        if ($message_id < 1) {
            // 診斷 log：非法 message_id
            hkai()->runtime()->logger()->warn("prompt_stop invalid: message_id=" . $message_id);

            // 判定：route 呼叫 → JSON 400；內部呼叫 → false
            if ($is_request_call) {
                return sc_response()->json(["ok" => false, "error" => "invalid message"], 400);
            } else {
                return false;
            }
        }

        // 以 message_id 反查 chat_id（不信任前端）
        $message_row = sc_ai_chats()->message()->repo_select()->get_message_row($message_id);

        // 判定：訊息不存在 → 回錯誤
        if ($message_row === null) {
            // 判定：route 呼叫 → JSON 404；內部呼叫 → false
            if ($is_request_call) {
                return sc_response()->json(["ok" => false, "error" => "message not found"], 404);
            } else {
                return false;
            }
        }

        $chat_id = (int)$message_row["chat_id"];
        $prompt_id = (string)($message_row["prompt_id"] ?? "");

        // 對話列（chat_sid——三層目錄身份）+ 專案 slug（路徑唯一來源）
        $chat_row = sc_ai_chats()->chat()->get_chat_row($chat_id);
        $chat_sid = (string)($chat_row["chat_sid"] ?? "");
        $project_slug = sc_ai_chats()->poll()->get_project_slug_by_chat($chat_id);

        $box_dir = "";

        // 判定：身份齊全 → 直組箱路徑（零 DB 反查）
        if ($prompt_id !== "" && $chat_sid !== "" && $project_slug !== "") {
            $box_dir = sc_ai_chats()->poll()->get_poll_dir_by_identity($project_slug, $chat_sid, $prompt_id);
        }

        // 判定：紙箱已唔喺 polling（已歸檔／已刪）→ 唔喺運送中，零寫入直接回
        if ($box_dir === "" || is_dir($box_dir) === false) {
            hkai()->runtime()->logger()->info("prompt_stop ignored: not in flight | message_id=" . $message_id . " | prompt_id=" . $prompt_id);

            // 判定：route 呼叫 → 200（已成終態，唔當錯）；內部呼叫 → false
            if ($is_request_call) {
                return sc_response()->json(["ok" => true, "stopped" => false, "message_id" => $message_id]);
            } else {
                return false;
            }
        }

        // 活動列收尾：最後一行留取消足跡（前端顯示 [cancelled]）
        // 必須搶喺狀態落地之前寫入——狀態一寫 cancelled，任何 poll 拍都可能會歸檔移走目錄
        $msg_log_file = $box_dir . "/msg_log.txt";
        if (file_exists($msg_log_file)) {
            sc_ai_chats()->poll()->log()->append_log($chat_id, $message_id, "Cancelled by user", ["event" => "CANCELLATION_REQUESTED"]);
        }

        // 狀態：CANCELLATION_REQUESTED → cancelled（fsm 對應表；箱路徑直傳——零反查）
        $next_status = sc_ai_chats()->poll()->append_msg_status_by_event($chat_id, $message_id, "CANCELLATION_REQUESTED", $box_dir);

        // 圖片抽取中（箱內有 extract.pid）→ 叫 bridge 停 extractor（idempotent）
        // 判定：箱內有抽取進程 → 停（殺進程樹＋交還認領；已完成者保留）
        if (is_file($box_dir . "/extract.pid")) {
            hkai()->process()->bridge()->request("/image_extract/stop/", ["box_dir" => $box_dir], 20);
        }

        // 精準終止該訊息的子程序（只殺本訊息——唔會誤殺同對話其他訊息）
        sc_ai_chats()->process()->kill_message_children($chat_id, $message_id);

        // 判定：route 呼叫 → 回 JSON；內部呼叫 → 回 true
        if ($is_request_call) {
            $response_json = [];
            $response_json["ok"]           = true;
            $response_json["status"]       = $next_status;
            $response_json["chat_deleted"] = false;

            return sc_response()->json($response_json);
        } else {
            return true;
        }
    }

}
