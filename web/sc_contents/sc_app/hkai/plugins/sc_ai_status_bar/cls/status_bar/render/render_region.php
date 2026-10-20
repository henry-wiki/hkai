<?php

namespace sc\app\hkai\plugins\sc_ai_status_bar\status_bar\render;

// == sc_ai_status_bar main render_region：域動作（trait——掛入 main_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat sc_ai_status_bar_main 分遷入（one file one action） ==

trait render_region
{
    // 產生單一欄的 status bar HTML（三欄各自嵌一條——唔再有全局 fixed footer）
    // 用法：sc_ai_status_bar()->status_bar()->render_region("project")／("chat")／("workspace")
    public function render_region($region_key)
    {
        // 外部貢獻（add_status_item）先套用——納入 groups／item_states 後才輸出
        $this->apply_status_items();
        
        // region 不存在 → 不輸出（呼叫端安全）
        if (!isset($this->groups[$region_key])) {
            return "";
        }
        
        $group_config = $this->groups[$region_key];
        
        $output_lines   = [];
        $output_lines[] = "<div class='sc_statusbar sc_statusbar--" . $region_key . "'>";
        
        // 有 subs 的 group（main）→ 逐 sub 輸出（left／right）
        if (isset($group_config["subs"])) {
            foreach ($group_config["subs"] as $sub_id => $sub_config) {
                $output_lines[] = "<div class='sc_statusbar__" . $sub_id . "'>";
                
                // items 依 priority 排序後輸出
                $sub_items = $this->sort_items_by_priority($sub_config["items"]);
                foreach ($sub_items as $item_id => $item_config) {
                    $output_lines[] = $this->render_item($item_id, $item_config);
                }
                
                // 自由 HTML 內容（provider 原樣插入——接喺 config items 之後）
                if (isset($sub_config["html_items"])) {
                    foreach ($sub_config["html_items"] as $html_item) {
                        $output_lines[] = $html_item;
                    }
                }

                // sub 掛載槽（外部模組注入用）
                if (isset($sub_config["slot"])) {
                    $output_lines[] = $this->render_slot($sub_config["slot"]);
                }
                
                $output_lines[] = "</div>";
            }
        }
        // 直接有 items 的 group（project、chat）→ 直接輸出
        else {
            // items 依 priority 排序後輸出
            $group_items = $this->sort_items_by_priority($group_config["items"]);
            foreach ($group_items as $item_id => $item_config) {
                $output_lines[] = $this->render_item($item_id, $item_config);
            }
            
            // 自由 HTML 內容（provider 原樣插入——接喺 config items 之後）
            if (isset($group_config["html_items"])) {
                foreach ($group_config["html_items"] as $html_item) {
                    $output_lines[] = $html_item;
                }
            }

            // group 掛載槽（外部模組注入用）
            if (isset($group_config["slot"])) {
                $output_lines[] = $this->render_slot($group_config["slot"]);
            }
        }
        
        $output_lines[] = "</div>";
        
        return implode("\r\n", $output_lines);
    }
}
