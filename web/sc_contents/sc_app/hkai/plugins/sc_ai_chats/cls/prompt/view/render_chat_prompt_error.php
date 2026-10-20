<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\view;

// == sc_ai_chats prompt render_chat_prompt_error：prompt 域動作（trait——掛入 prompt_Class；檔＝動作：prompt 區塊錯誤（錯 sid——對話查唔到）） ==

trait render_chat_prompt_error
{
    // prompt 區塊錯誤（錯 sid——回唔到對話；簡單紅字錯誤訊息——i18n 文案；唔渲染輸入區）
    public function render_chat_prompt_error()
    {
        return "<div class='sc_composer__error'>" . hkai__i18n()->get("error.chat_not_found") . "</div>";
    }
}
