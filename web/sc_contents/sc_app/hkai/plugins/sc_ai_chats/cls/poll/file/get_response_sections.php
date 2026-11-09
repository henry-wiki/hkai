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
        $poll_list_map = sc_ai_chats()->poll()->file()->get_msg_poll_list($chat_id, $message_id);

        $message_dir = sc_ai_chats()->poll()->get_existing_message_dir($message_id);

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

            // 段狀態＝poll_list entry（Python 唯一寫入者）；舊記錄 fallback poll_status
            $entry_status = (string)($poll_list_entry_item["status"] ?? "");

            // 判定：舊記錄（冇 status 欄）→ 用 poll_status
            if ($entry_status === "") {
                $entry_status = (string)($poll_list_entry_item["poll_status"] ?? "poll");
            }

            $poll_list_entry_item["poll_status"] = $entry_status;

            $response_section_list[] = $poll_list_entry_item;
        }

        return $response_section_list;
    }
}
