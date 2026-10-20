<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message get_agent_response_result_toolbar：message 域動作（trait——掛入 view_Class；檔＝動作：result 工具列） ==

trait get_agent_response_result_toolbar
{
    // 砌 result 工具列（動作組＝放大／複製＋模型；右＝用時——有才出）
    public function get_agent_response_result_toolbar($message_model_text, $response_elapsed_sec = null, $response_id = "")
    {
        $content = [];
        $content[] = "<div class='seg__bar'>";
            $content[] = "<div class='seg__bar_actions'>";
            $zoom_title = hkai__i18n()->get("answer.zoom");
            $zoom_modal_title = hkai__i18n()->get("answer.zoom_title");
            $btn_attr = [];
            $btn_attr[] = "data-action='response_zoom'";
            $btn_attr[] = "data-zoom_scope='[data-message_id]'";
            $btn_attr[] = "data-zoom_target='[data-text=result]'";
            $btn_attr[] = "data-zoom_title='{$zoom_modal_title}'";
            $btn_attr[] = "class='seg__action'";
            $btn_attr[] = "title='{$zoom_title}'";

            $btn_attr = implode(" ", $btn_attr);
            $content[] = "<button {$btn_attr}>";
            $content[] = "<i class='fa-solid fa-up-right-and-down-left-from-center'></i>";
            $content[] = "</button>";
            $copy_title = hkai__i18n()->get("answer.copy");
            $btn_attr = [];
            $btn_attr[] = "data-action='message_copy'";
            $btn_attr[] = "data-copy_scope='[data-message_id]'";
            $btn_attr[] = "data-copy_target='[data-text=result]'";
            $btn_attr[] = "class='seg__action'";
            $btn_attr[] = "title='{$copy_title}'";
            
            $btn_attr = implode(" ", $btn_attr);
            $content[] = "<button {$btn_attr}>";
            $content[] = "<i class='fa-regular fa-copy'></i>";
            $content[] = "</button>";

            // 判定：有模型資訊 → 出模型（plain——provider/model；copy 之後）
            if ($message_model_text !== "") {
                $content[] = "<span class='message_model'>{$message_model_text}</span>";
            }
            $content[] = "</div>";

            // 判定：有段用時（秒）→ 出計時器（右側——m:ss；data 供前端每秒遞增）
            if ($response_elapsed_sec !== null) {
                $response_elapsed_text = sc_ai_chats()->message()->view()->format_elapsed_sec($response_elapsed_sec);

                $content[] = "<span class='response_elapsed' data-response_id='{$response_id}' data-elapsed_sec='{$response_elapsed_sec}' data-elapsed_ts='0'>{$response_elapsed_text}</span>";
            }
        $content[] = "</div>";

        return implode("",$content);
    }

}
