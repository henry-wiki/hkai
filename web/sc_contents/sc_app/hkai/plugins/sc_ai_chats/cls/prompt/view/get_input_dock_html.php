<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\view;

// == sc_ai_chats prompt get_input_dock_html：prompt 域動作（trait——掛入 prompt_Class；檔＝動作：區塊格2：input dock 內容（附件／文字／工具列）） ==
// == 2026-09-27 自 prompt/view.php 分獨立檔（one file one function） ==

trait get_input_dock_html
{

    // prompt 區塊格2：input dock 內容（附件／文字／工具列——主按鈕初始態由後端決定）
    public function get_input_dock_html($chat_sid, $prompt_draft = null)
    {
        // 對話 id 解析（主按鈕初始態用——未選／查無＝冇工作中）
        $chat_id = 0;
        if ((string)$chat_sid !== "") {
            $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);
            
            // 判定：對話存在 → 取 chat_id
            if ($chat_row !== null) {
                $chat_id = (int)$chat_row["chat_id"];
            }
        }
        
        // 主按鈕初始態（後端判準——工作中＝Stop 先出，載入零閃）
        $is_working = false;
        if ($chat_id > 0) {
            $is_working = sc_ai_chats()->prompt()->is_prompt_working($chat_id);
        }
        
        // 主按鈕屬性（一元素一組、array 收集 → implode 一次過）
        $btn_send_attr = [];
        $btn_send_attr[] = "data-action='send'";
        $btn_send_attr[] = "type='button'";
        $btn_send_attr[] = "class='sc_btn sc_btn--primary sc_btn--sm'";
        $btn_send_attr[] = "title='Send'";
        $btn_send_attr[] = "aria-label='Send'";
        
        $btn_stop_attr = [];
        $btn_stop_attr[] = "data-action='stop'";
        $btn_stop_attr[] = "type='button'";
        $btn_stop_attr[] = "class='sc_btn sc_btn--danger sc_btn--sm'";
        $btn_stop_attr[] = "title='Stop'";
        $btn_stop_attr[] = "aria-label='Stop'";
        
        // 判定：工作中 → Stop 出、Send 藏；閒置 → 相反（hidden 只喺要藏嗰粒加）
        if ($is_working === true) {
            $btn_send_attr[] = "hidden";
        } else {
            $btn_stop_attr[] = "hidden";
        }
        
        $btn_send_attr = implode(" ", $btn_send_attr);
        $btn_stop_attr = implode(" ", $btn_stop_attr);
        
        // 輸入框佔位文案（i18n——唔寫死）
        $input_placeholder = hkai__i18n()->get("prompt.input_placeholder");
        
        // 草稿文字（活動 prompt_draft——有草稿回填輸入框；無＝空；轉義一次過）
        $draft_text = "";
        // 判定：活動有草稿 → 取文字
        if ($prompt_draft !== null) {
            $draft_text = $prompt_draft["text"];
        }
        
        $draft_text_html = sc_html_encode($draft_text);
        
        $output_lines = [];
        // 槽1：附件暫存區（hidden by default）
        $output_lines[] = "<div class='sc_composer__attachments' hidden></div>";
        // 槽2：文字輸入主體
        $output_lines[] = "<div class='sc_composer__field'>";
            $output_lines[] = "<textarea id='sc_input' rows='1' placeholder='{$input_placeholder}' aria-label='Input message'>{$draft_text_html}</textarea>";
        $output_lines[] = "</div>";
        // 槽3：底部工具列
        $output_lines[] = "<div class='sc_composer__toolbar'>";
            // 左側工具位（leading——現時空置嘅預留容器：將來嘅左側工具〔如附件／語音〕落呢度；容器唔拆）
            $output_lines[] = "<div class='sc_composer__tools_leading'>";
                $output_lines[] = "<span class='sc_composer__badge'></span>";
            $output_lines[] = "</div>";
            // 右側工具（send／stop 一對——btn wrapper 包住；同時只顯示一個：有字＝send；無字＋工作中＝stop）
            $output_lines[] = "<div class='sc_composer__tools_trailing'>";
                $output_lines[] = "<div class='sc_composer__send_group'>";
                    $output_lines[] = "<button {$btn_stop_attr}><i class='fa-solid fa-stop'></i></button>";
                    $output_lines[] = "<button {$btn_send_attr}><i class='fa-solid fa-arrow-up'></i></button>";
                $output_lines[] = "</div>";
            $output_lines[] = "</div>";
        $output_lines[] = "</div>";
        
        return implode("\r\n", $output_lines);
    }
}
