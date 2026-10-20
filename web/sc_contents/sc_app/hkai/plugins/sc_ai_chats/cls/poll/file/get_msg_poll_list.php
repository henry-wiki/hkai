<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\file;

// == sc_ai_chats poll file get_msg_poll_list：file 子域動作（trait——合成入 file_Class；檔＝動作） ==
// == 2026-10-05 新制：msg_poll_list.jsonl 由 Python 獨家寫；PHP 純讀（唔再寫唔再 update） ==
// == 讀入 key＝poll_file；拆檔名補 response_type／response_index（msg_{family}.{index}.{kind}.txt） ==

trait get_msg_poll_list
{

    //---------------------------------------------------------
    // 1. 讀 msg_poll_list.jsonl（冇檔＝空 map）
    // 2. 逐行：拆 poll_file 檔名 → response_type／response_index
    // 3. key＝poll_file 回 map（舊制行——冇 poll_file——跳過）
    //---------------------------------------------------------
    public function get_msg_poll_list($chat_id, $message_id)
    {
        $poll_list_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "poll_list");
        $poll_list_map  = [];

        // 判定：對照檔唔存在 → 空 map
        if (!is_file($poll_list_file)) {
            return $poll_list_map;
        }

        $poll_list_lines = explode("\n", (string)@file_get_contents($poll_list_file));

        // 逐行解析
        foreach ($poll_list_lines as $poll_list_line_item) {
            $trimmed_poll_list_line = trim($poll_list_line_item);

            // 判定：空行 → 跳過
            if ($trimmed_poll_list_line === "") {
                continue;
            }

            $poll_list_entry = json_decode($trimmed_poll_list_line, true);

            // 判定：唔係 JSON 物件 → 跳過
            if (!is_array($poll_list_entry)) {
                continue;
            }

            $entry_poll_file = (string)($poll_list_entry["poll_file"] ?? "");

            // 判定：舊制行（冇 poll_file）→ 跳過
            if ($entry_poll_file === "") {
                continue;
            }

            // 拆檔名（msg_{family}.{index}.{kind}.txt）——normalize 子域兩支（單一來源）
            $poll_list_entry["response_type"]  = sc_ai_chats()->message()->normalize()->get_response_type_from_poll_file($entry_poll_file);
            $poll_list_entry["response_index"] = sc_ai_chats()->message()->normalize()->get_response_index_from_poll_file($entry_poll_file);

            $poll_list_map[$entry_poll_file] = $poll_list_entry;
        }

        return $poll_list_map;
    }

}
