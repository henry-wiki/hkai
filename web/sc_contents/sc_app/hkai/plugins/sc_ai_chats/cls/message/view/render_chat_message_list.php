<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message render_chat_message_list：message 域動作（trait——掛入 view_Class；檔＝動作） ==
// == 2026-09-27 自 message/view.php 分獨立檔（one file one function） ==

trait render_chat_message_list
{

    // 渲染整段對話（兩層制——逐條收集 {template, slots}；唔回填，內容交前端灌入；依傳入順序輸出，user 列與其回答同 wrapper）
    public function render_chat_message_list($message_rows, $message_response_rows,$display_summarize=false,$show_log=false)
    {
        $message_entries = [];

        // 逐訊息收集（已無 agent 列——回答由 get_agent_response 傳入 message_response_rows）
        foreach ($message_rows as $message_row_item) {
            $message_id = (int)$message_row_item["message_id"];

            $message_response_rows_for_message = $message_response_rows[$message_id] ?? [];

            $message_entries[] = sc_ai_chats()->message()->view()->get_chat_message_slot($message_row_item, $message_response_rows_for_message, $display_summarize, $show_log);
        }

        return $message_entries;
    }

}
