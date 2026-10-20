<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message get_agent_response_thinking_toolbar：message 域動作（trait——掛入 view_Class；檔＝動作：thinking 工具列） ==

trait get_agent_response_thinking_toolbar
{
    // 砌 thinking 工具列（動作鈕——段右上；用時——段右下；兩個獨立容器）
    public function get_agent_response_thinking_toolbar($response_elapsed_sec = null, $response_id = "")
    {
        $content = [];
        $content[] = "<div class='seg__bar'>";
            $zoom_title = hkai__i18n()->get("answer.zoom");
            $zoom_modal_title = hkai__i18n()->get("thinking.label_done");
            $btn_attr = [];
            $btn_attr[] = "type='button'";
            $btn_attr[] = "class='seg__action'";
            $btn_attr[] = "data-action='thinking_zoom'";
            $btn_attr[] = "data-zoom_scope='.seg--thinking'";
            $btn_attr[] = "data-zoom_target='[data-text=thinking]'";
            $btn_attr[] = "data-zoom_title='{$zoom_modal_title}'";
            $btn_attr[] = "title='{$zoom_title}'";

            $btn_attr = implode(" ", $btn_attr);
            $content[] = "<button {$btn_attr}>";
            $content[] = "<i class='fa-solid fa-up-right-and-down-left-from-center'></i>";
            $content[] = "</button>";
            $copy_title = hkai__i18n()->get("answer.copy");
            $btn_attr = [];
            $btn_attr[] = "type='button'";
            $btn_attr[] = "class='seg__action'";
            $btn_attr[] = "data-action='message_copy'";
            $btn_attr[] = "data-copy_scope='.seg--thinking'";
            $btn_attr[] = "data-copy_target='[data-text=thinking]'";
            $btn_attr[] = "title='{$copy_title}'";

            $btn_attr = implode(" ", $btn_attr);
            $content[] = "<button {$btn_attr}>";
            $content[] = "<i class='fa-regular fa-copy'></i>";
            $content[] = "</button>";
        $content[] = "</div>";
        $content[] = "<div class='seg__elapsed'>";
            // 判定：有段用時（秒）→ 出計時器（m:ss；data 供前端每秒遞增）
            if ($response_elapsed_sec !== null) {
                $response_elapsed_text = sc_ai_chats()->message()->view()->format_elapsed_sec($response_elapsed_sec);

                $content[] = "<span class='response_elapsed' data-response_id='{$response_id}' data-elapsed_sec='{$response_elapsed_sec}' data-elapsed_ts='0'>{$response_elapsed_text}</span>";
            }
        $content[] = "</div>";

        return implode("",$content);
    }

}
