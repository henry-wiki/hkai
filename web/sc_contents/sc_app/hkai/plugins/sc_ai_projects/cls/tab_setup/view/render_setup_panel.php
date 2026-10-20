<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_setup\view;

// == sc_ai_projects tab_setup render_setup_panel：tab_setup 域動作（trait——掛入 tab_setup_Class；檔＝動作） ==
// == 2026-09-27 自 tab_setup/view.php 分遷入（one file one action） ==

trait render_setup_panel
{
    public function render_setup_panel($checklist_result)
    {
        $outputLines   = [];
        
        $outputLines[] = "<p class='check_intro'>檢查這台機器與目前專案路徑的環境。通過的項目會顯示 ✓；與專案有關、尚未完成的項目可在右側操作。</p>";
        
        // 依 group 分組輸出（同一組只輸出一次標題）
        $current_group = null;
        foreach ($checklist_result["items"] as $check_item) {
            // 換組 → 輸出分組標題
            if ($check_item["group"] !== $current_group) {
                $current_group = $check_item["group"];
                $outputLines[] = "<div class='check_group'>" . $current_group . "</div>";
            }
            
            $outputLines[] = $this->render_check_item($check_item);
        }
        
        return implode("\r\n", $outputLines);
    }
}
