<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message view get_message_template_html：訊息氣泡模板（**唔另砌 markup——委派 get_chat_message_slot**） ==
// == 職責：用空資料＋終態 status 叫 get_chat_message_slot()，並灌好 user 槽（樂觀泡泡外觀同真氣泡一致） ==
// == 上游：render_chat_prompt（base64 隱藏交付入 composer） ==
// == 下游：get_chat_message_slot（唯一骨架產生者）

trait get_message_template_html
{
    // 訊息氣泡骨架（唯一真相＝get_chat_message_slot 的 template ＋ user 槽）
    public function get_message_template_html()
    {
        // 用終態 status（completed）→ 唔會出活動日誌／用時列骨架（樂觀泡泡唔要假數據）
        $message_row = [];
        $message_row["message_status"] = "completed";

        $message_render = sc_ai_chats()->message()->view()->get_chat_message_slot($message_row, [], false, false);

        $message_template_html = (string)($message_render["template"]["message"] ?? "");
        $user_message_html    = (string)($message_render["slots"]["user_message"] ?? "");

        // 灌入 user 槽（同前端 replace_slots 同一機制——佔位容器換成 slot 內容，氣泡外觀才正確）
        $user_slot_placeholder = "<div class='user_message' data-slot='user_message'></div>";
        $message_template_html = str_replace($user_slot_placeholder, $user_message_html, $message_template_html);

        return $message_template_html;
    }
}
