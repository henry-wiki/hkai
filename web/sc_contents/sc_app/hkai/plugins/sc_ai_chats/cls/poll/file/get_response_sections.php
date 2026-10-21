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

            // 段狀態＝訊息狀態（唯一來源＝msg_status；poll_list 只記時間／長度，唔代判完成）
            if ($message_poll_status === "completed") {
                $poll_list_entry_item["poll_status"] = "completed";
            } elseif (in_array($message_poll_status, ["failed", "cancelled"])) {
                $poll_list_entry_item["poll_status"] = "aborted";
            } else {
                $poll_list_entry_item["poll_status"] = "poll";
            }

            $response_section_list[] = $poll_list_entry_item;
        }

        // 判定：唔係失敗 → 唔讀失敗記錄（成功／進行中一律無 error 段）
        if ($message_poll_status !== "failed") {
            return $response_section_list;
        }

        // 失敗記錄（msg_log 最後一條 FAILURE_RECORDED＝失敗原因——活動記錄為單一來源）
        $log_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "log");

        // 判定：polling 位置冇（訊息已歸檔）→ 改用歸檔目錄同一個檔名
        if (!file_exists($log_file)) {
            $archived_message_dir = sc_ai_chats()->poll()->get_archived_poll_dir($chat_id, $message_id);

            $log_file = $archived_message_dir . "/" . basename($log_file);
        }

        $log_raw = "";
        if (file_exists($log_file)) {
            $log_raw = (string)@file_get_contents($log_file);
        }

        $error_message = "";

        // 逐行解析（最後一條 FAILURE_RECORDED 為準——壞行跳過唔阻流程）
        $log_normalized = str_replace(chr(13), "", $log_raw);

        foreach (explode(chr(10), $log_normalized) as $log_line_item) {
            $trimmed_log_line = trim($log_line_item);

            // 判定：空行 → 跳過
            if ($trimmed_log_line === "") {
                continue;
            }

            $log_event = json_decode($trimmed_log_line, true);

            // 判定：唔係 JSON 物件 → 跳過
            if (!is_array($log_event)) {
                continue;
            }

            // 判定：失敗事件且有 message → 記低（續掃——最後一條為準）
            if (isset($log_event["level"]) && strtoupper((string)$log_event["level"]) === "ERROR" && isset($log_event["message"])) {
                $error_message = (string)$log_event["message"];
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
