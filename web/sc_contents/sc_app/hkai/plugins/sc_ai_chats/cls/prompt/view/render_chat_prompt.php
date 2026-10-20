<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\view;

// == sc_ai_chats prompt render_chat_prompt：prompt 域動作（trait——掛入 prompt_Class；檔＝動作：prompt 區塊內裏整包（三格——交前端換入；外層 .sc_composer 長駐，喺 render_message_body）） ==
// == 2026-09-27 自 prompt/view.php 分獨立檔（one file one function） ==

trait render_chat_prompt
{

    // prompt 區塊內裏整包（三格——外層 .sc_composer 頁面長駐；本包交前端換入（prompt_fetch 回應））
    public function render_chat_prompt($project_slug, $chat_sid)
    {
        // 草稿活動讀取（prompt_draft——單一讀取、帶落兩個子視圖）
        $prompt_draft = sc_ai_chats()->prompt()->get_prompt_draft($chat_sid);

        $input_stack_html  = sc_ai_chats()->prompt()->render_input_stack($chat_sid);
        $prompt_toolbar_html = sc_ai_chats()->prompt()->get_prompt_toolbar_html();
        $input_dock_html   = sc_ai_chats()->prompt()->get_input_dock_html($chat_sid, $prompt_draft);
        $chat_options_html = sc_ai_chats()->prompt()->get_chat_options_html($project_slug, $chat_sid, $prompt_draft);

        $composer_parts   = [];
        // 格1：緊急槽（等待列——自帶外框）
        $composer_parts[] = $input_stack_html;
        // 格2：上工具列（附件／加入內容／最頂／最底／清除排隊／靈感問題——盒上一行）
        $composer_parts[] = $prompt_toolbar_html;
        // 格3：輸入盒（附件 / 文字 / 工具欄＋模式鈕 / 模型顯示 / hidden——同一盒；底行由 CSS grid 編排（跟 reference3 seamless：輸入上、mode 左下、send 右下））
        $composer_parts[] = "<div class='sc_composer__editor'>";
            $composer_parts[] = $input_dock_html;
            $composer_parts[] = "<div class='sc_composer__options'>";
                $composer_parts[] = $chat_options_html;
            $composer_parts[] = "</div>";
        $composer_parts[] = "</div>";

        return implode("\r\n", $composer_parts);
    }
}
