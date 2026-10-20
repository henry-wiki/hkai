<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message get_user_prompt：message 域動作（trait——掛入 view_Class；檔＝動作） ==
// == 2026-09-27 自 chat/view.php 分獨立檔；2026-09-27 歸 message 域（訊息渲染） ==

trait get_user_prompt
{
    // 使用者訊息氣泡 HTML（內容＋工具列：fav／forward／zoom／copy／delete）
    public function get_user_prompt($row, $message_model_text = "")
    {
        $message_content = (isset($row["message_content"]) ? $row["message_content"] : null);
        $user_zoom_title = hkai__i18n()->get("user.zoom_title");
        $answer_zoom     = hkai__i18n()->get("answer.zoom");
        $answer_copy     = hkai__i18n()->get("answer.copy");
        $delete_confirm  = hkai__i18n()->get("message.delete_confirm");

        // 模型顯示 HTML（plain——provider/model；空＝唔出）
        $message_model_html = "";
        // 判定：有模型資訊 → 砌 span（copy 之後）
        if ($message_model_text !== "") {
            $message_model_html = "<span class='message_model'>{$message_model_text}</span>";
        }
        
        // 顯示層只放純變數（取值／i18n 一律先落變數——唔內嵌）
        $content = [];
        $content[] = "<div class='user_prompt' data-text='user_content'>{$message_content}</div>";
        $content[] = "<div class='user_prompt_bar'>";
            $content[] = "{$message_model_html}";
            $content[] = "
            <button data-action='prompt_fav' class='prompt_action' title='Favorite' style='display:none;'>
            <i class='fa-regular fa-star'></i>
            </button>
            <button data-action='prompt_forward' class='prompt_action' title='Forward' style='display:none;'>
            <i class='fa-solid fa-share'></i>
            </button>
            <button data-action='user_zoom' data-zoom_scope='[data-message_id]' data-zoom_target='[data-text=user_content]' data-zoom_title='{$user_zoom_title}' class='prompt_action' title='{$answer_zoom}'>
            <i class='fa-solid fa-up-right-and-down-left-from-center'></i>
            </button>
            <button data-action='message_copy' data-copy_scope='[data-message_id]' data-copy_target='[data-text=user_content]' class='prompt_action' title='{$answer_copy}'>
            <i class='fa-regular fa-copy'></i>
            </button>
            <button data-action='message_delete' data-confirm_msg='{$delete_confirm}' class='prompt_action' title='Delete'>
            <i class='fa-regular fa-trash-can'></i>
            </button>
            ";
        $content[] = "</div>";
        
        return implode("", $content);
    }

}
