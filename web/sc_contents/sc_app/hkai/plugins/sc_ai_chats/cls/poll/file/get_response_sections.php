<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\file;

// == sc_ai_chats poll file get_response_sections：file 子域動作（trait——合成入 file_Class；檔＝動作） ==
// == 2026-10-05 新制：逐 poll 對照線（Python 寫——唯一事實）直接食條目＋補 response_content；唔排序 ==

trait get_response_sections
{

    //---------------------------------------------------------
    // 1. 讀 poll 對照（Python 寫；key＝poll_file——唯一事實）
    // 2. 逐線：直接食條目（JSON 原文）＋補 response_content
    // 3. 失敗訊息：再吐 error 段（error_log 最後一條 message）
    //---------------------------------------------------------
    public function get_response_sections($chat_id, $message_id, $message_poll_status)
    {
        $response_section_list = [];

        // poll 對照（Python 寫；key＝poll_file——拆段已補 response_type／response_index）
        $poll_list_map = $this->get_msg_poll_list($chat_id, $message_id);

        $message_dir = sc_ai_chats()->poll()->get_poll_dir($chat_id, $message_id);

        // 逐對照線：直接食條目（JSON 原文）＋補 response_content
        foreach ($poll_list_map as $poll_list_entry_item) {
            $response_file_path = $message_dir . "/" . $poll_list_entry_item["poll_file"];

            // 判定：檔唔在 → 跳過
            if (!is_file($response_file_path)) {
                continue;
            }

            $response_content = (string)@file_get_contents($response_file_path);

            // 判定：無內容 → 唔吐（空殼）
            if ($response_content === "") {
                continue;
            }

            $poll_list_entry_item["response_content"] = $response_content;

            $response_section_list[] = $poll_list_entry_item;
        }

        // 判定：唔係失敗 → 唔讀失敗記錄（成功／進行中一律無 error 段）
        if ($message_poll_status !== "failed") {
            return $response_section_list;
        }

        // 失敗記錄（error_log jsonl——最後一條有 message 嘅行＝失敗原因）
        $error_log_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "error_log");

        $error_log_raw = "";
        if (file_exists($error_log_file)) {
            $error_log_raw = (string)@file_get_contents($error_log_file);
        }

        $error_log_lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $error_log_raw));

        $error_message = "";

        // 逐行解析（最後一條 message 為準——壞行跳過唔阻流程）
        foreach ($error_log_lines as $error_log_line_item) {
            $trimmed_error_log_line = trim($error_log_line_item);

            // 判定：空行 → 跳過
            if ($trimmed_error_log_line === "") {
                continue;
            }

            $error_log_event = json_decode($trimmed_error_log_line, true);

            // 判定：唔係 JSON 物件 → 跳過
            if (!is_array($error_log_event)) {
                continue;
            }

            // 判定：有 message → 記低（續掃——最後一條為準）
            if (isset($error_log_event["message"])) {
                $error_message = (string)$error_log_event["message"];
            }
        }

        // 判定：有失敗原因 → 落 error 段（渲染段 id＝error；刷新後仍見）
        if ($error_message !== "") {
            $response_section_list[] = [
                "response_type"        => "error",
                "response_content"     => $error_message,
                "response_index"       => 0,
                "poll_file"            => "",
                "poll_status"          => "aborted",
                "poll_content_length"  => strlen($error_message),
            ];
        }

        return $response_section_list;
    }

}
