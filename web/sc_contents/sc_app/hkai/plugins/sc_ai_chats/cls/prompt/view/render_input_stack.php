<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\view;

// == sc_ai_chats prompt render_input_stack：prompt 域動作（trait——掛入 prompt_Class；檔＝動作：區塊格3 input stack 整個元素（等待列 UI）） ==
// == 2026-09-27 自 prompt/view.php 分獨立檔（one file one function） ==

trait render_input_stack
{

    // prompt 區塊格3：input stack 整個元素（等待列 UI——未選對話＝隱藏空容器）
    public function render_input_stack($chat_sid)
    {
        $queue_list = $this->render_empty_queue_list();
        
        // 判定：已選對話 → 掃等待列砌完整 UI（與 submit 共用同一 get_queue_lines＋render_queue_list）
        if ((string)$chat_sid !== "") {
            $selected_chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);
            
            // 判定：對話列存在 → 掃等待列砌 UI
            if ($selected_chat_row !== null) {
                $chat_id = (int)$selected_chat_row["chat_id"];
                
                $queue_list = sc_ai_chats()->prompt()->render_queue_list($chat_id);
            }
        }
        
        $output_lines = [];
        $output_lines[] = "<div class='sc_composer__queue'>";
            $output_lines[] = $queue_list;
            // 等待列工具列（留位——按鈕後續加入）
            $output_lines[] = "<div class='sc_composer__queue_toolbar'></div>";
        $output_lines[] = "</div>";
        
        return implode("\r\n", $output_lines);
    }
}
