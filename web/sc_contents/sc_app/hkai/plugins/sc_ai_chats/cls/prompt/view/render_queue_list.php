<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\view;

// == sc_ai_chats prompt render_queue_list：prompt 域動作（trait——掛入 prompt_Class；檔＝動作：完整等待列 UI（chips——四處共用同一渲染源）） ==
// == 2026-09-27 自 prompt/view.php 分獨立檔（one file one function） ==

trait render_queue_list
{
    // 完整等待列 UI（整排 chips——頁面初始／message_list_fetch／submit 回應／poll 每拍共用）
    // 傳入 chat_id——內部掃等待列（message_queue get_queue_lines 單一掃描來源）
    // 前端只 bind（replaceWith）——一律唔砌 UI；空＝hidden 空容器
    public function render_queue_list($chat_id)
    {
        $queue_lines = sc_ai_chats()->queue()->get_queue_lines($chat_id);
        
        $cancel_label = sc_html_encode(hkai__i18n()->get("queue.cancel"));
        
        $output_lines = [];
        
        // 判定：冇排隊中訊息 → 隱藏空容器（與頁面初始標記一致）
        if (count($queue_lines) < 1) {
            $output_lines[] = $this->render_empty_queue_list();
            
            return implode("\r\n", $output_lines);
        }
        
        $output_lines[] = "<div class='sc_composer__queue_list'>";
            
            // 逐項建 chip（轉義整個資料結構一次過——先落同名變數再入字串）
            foreach ($queue_lines as $queue_line_item) {
                $queue_line_item = sc_html_encode($queue_line_item);
                
                $queue_uid     = (string)$queue_line_item["queue_uid"];
                $queue_content = (string)$queue_line_item["content"];
                
                $output_lines[] = "    <div class='queue_item' data-queue_uid='" . $queue_uid . "'>";
                $output_lines[] = "        <span class='queue_text'>" . $queue_content . "</span>";
                $output_lines[] = "        <button type='button' class='queue_cancel' data-action='queue_cancel' title='" . $cancel_label . "'>×</button>";
        $output_lines[] = "    </div>";
    }
    
    $output_lines[] = "</div>";
    
    return implode("\r\n", $output_lines);
}
}
