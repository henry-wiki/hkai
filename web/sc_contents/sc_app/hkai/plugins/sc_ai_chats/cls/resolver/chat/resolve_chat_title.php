<?php

namespace sc\app\hkai\plugins\sc_ai_chats\resolver\chat;

// == sc_ai_chats resolver\chat resolve_chat_title：對話解析（trait——掛入 sc_ai_chats_Class；檔＝動作） ==
// == 職責：由訊息內容砌對話標題（壓空白、截 200 字；唔加省略號——顯示層自理） ==
// == 上游：只供 prompt_send 落地① 用（送出先有第一句內容＝標題）；其他入口暫無需 ==
// == 下游：無（純函數——零 IO） ==

trait resolve_chat_title
{
    // 建立對話標題（現階段只用於 prompt 送出；將來要重用先再擴大入口）
    public function resolve_chat_title($content)
    {
        $chat_title_max_chars = 200;
        $title_text           = preg_replace('/\s+/u', ' ', trim($content));

        // 判定：未超過上限 → 原樣回傳
        if (mb_strlen($title_text) <= $chat_title_max_chars) {
            return $title_text;
        }

        return mb_substr($title_text, 0, $chat_title_max_chars);
    }
}
