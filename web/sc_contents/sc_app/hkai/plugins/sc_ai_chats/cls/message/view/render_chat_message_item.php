<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message render_chat_message_item：message 域動作（trait——掛入 view_Class；檔＝動作） ==
// == 2026-09-27 自 message/view.php 分獨立檔（one file one function） ==

trait render_chat_message_item
{

    // 渲染單一問題列（字串——由 render 組合：空 slot 佔位換成實容器；供整批輸出如 message_list_fetch 用）
    public function render_chat_message_item($message_row = [], $message_response_rows = [], $display_summarize = false, $show_log = true)
    {
        $message_render = sc_ai_chats()->message()->view()->get_chat_message_slot($message_row, $message_response_rows, $display_summarize, $show_log);
        
        $message_html = $message_render["template"]["message"];
        
        // 逐 slot 回填（空容器 → 實容器——class 同名 slot key）
        foreach ($message_render["slots"] as $slot_key => $slot_html) {
            $slot_placeholder = "<div class='" . $slot_key . "' data-slot='" . $slot_key . "'></div>";
            $message_html = str_replace($slot_placeholder, $slot_html, $message_html);
        }
        
        return $message_html;
    }

}
