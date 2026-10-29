<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message view render_prompt_send_result：message 域視圖（trait——掛入 view_Class；cls/message/view/） ==
// == 職責：送出後 UI——本條進場出訊息氣泡＋隊列整排重砌（HTML 後端砌，前端只 bind） ==
// == 上游：prompt_send 工序 9；下游：get_chat_message_slot／prompt view render_queue_list ==

trait render_prompt_send_result
{
    // 砌送出後 UI（回 context：new_message／new_message_id／queue_list）
    public function render_prompt_send_result($context)
    {
        $chat_id      = $context["chat_id"];
        $prompt_id    = $context["prompt_id"];
        $shift_result = $context["shift_result"];

        $context["new_message"]    = null;
        $context["new_message_id"] = 0;

        // 判定：本條＝進場嗰條（shift 回傳 prompt_id 相同）→ 出氣泡模板
        if ($shift_result["prompt_id"] === $prompt_id) {
            $message_row = $shift_result["message_row"];

            $context["new_message"]    = sc_ai_chats()->message()->view()->get_chat_message_slot($message_row, [], true);
            $context["new_message_id"] = (int)$message_row["message_id"];
        }

        // 完整隊列 UI（共用 builder——傳 chat_id 即掃＋砌整排；前端只 bind）
        $context["queue_list"] = sc_ai_chats()->prompt()->render_queue_list($chat_id);

        return $context;
    }
}
