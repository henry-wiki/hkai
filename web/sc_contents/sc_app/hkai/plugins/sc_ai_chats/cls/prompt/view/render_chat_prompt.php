<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\view;

// == sc_ai_chats prompt render_chat_prompt：prompt 域動作（trait——掛入 prompt_Class；檔＝動作：prompt 區塊內裏整包（三格——交前端換入；外層 .sc_composer 長駐，喺 render_message_body）） ==
// == 2026-09-27 自 web/sc_contents/sc_app/hkai/plugins/sc_ai_settings/cls/view/view.php 分獨立檔（one file one function） ==

trait render_chat_prompt
{

    // prompt 區塊內裏整包（三格——外層 .sc_composer 頁面長駐；本包交前端換入（prompt_fetch 回應））
    public function render_chat_prompt($project_slug, $chat_sid)
    {
        $input_stack_html       = sc_ai_chats()->prompt()->render_input_stack($chat_sid);
        $prompt_toolbar_html    = sc_ai_chats()->prompt()->get_prompt_toolbar_html();
        $input_dock_html        = sc_ai_chats()->prompt()->get_input_dock_html($chat_sid);
        $chat_options_html      = sc_ai_chats()->prompt()->get_chat_options_html($project_slug, $chat_sid);

        // 模板資料（base64——隱藏 div 存放；前端發送／排隊時解碼取用）
        $message_template_html = sc_ai_chats()->message()->view()->get_message_template_html();
        $message_template_b64 = base64_encode($message_template_html);

        $queue_template_html = sc_ai_chats()->prompt()->get_queue_item_template_html();
        $queue_template_b64 = base64_encode($queue_template_html);

        $message_template_payload_html = "<div data-tpl='message_template' style='display:none;'>" . $message_template_b64 . "</div>";
        $queue_template_payload_html = "<div data-tpl='queue_list_template' style='display:none;'>" . $queue_template_b64 . "</div>";

        $composer_parts   = [];
        // 格1：緊急槽（等待列——自帶外框）
        $composer_parts[] = $input_stack_html;
        // 格2：上工具列（附件／加入內容／最頂／最底／清除排隊／靈感問題——盒上一行）
        $composer_parts[] = $prompt_toolbar_html;
        // 格3：輸入盒（附件 / 文字 / 工具欄＋模式鈕 / 模型顯示 / hidden——同一盒；底行由 CSS grid 編排（跟 reference3 seamless：輸入上、mode 左下、send 右下））
        $composer_parts[] = "<div class='sc_composer__editor'>";
            $composer_parts[] = $input_dock_html;
            // 模板資料（隱藏 div——唔顯示，只供前端取用）
            $composer_parts[] = $message_template_payload_html;
            $composer_parts[] = $queue_template_payload_html;
            $composer_parts[] = "<div class='sc_composer__options'>";
                $composer_parts[] = $chat_options_html;
            $composer_parts[] = "</div>";
        $composer_parts[] = "</div>";

        return implode("\r\n", $composer_parts);
    }
}
