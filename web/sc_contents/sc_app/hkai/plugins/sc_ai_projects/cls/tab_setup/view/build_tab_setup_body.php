<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_setup\view;

use function sc\app\hkai\plugins\sc_ai_projects\tab_setup\checks;

// == sc_ai_projects tab_setup build_tab_setup_body：tab_setup 域動作（trait——掛入 tab_setup_Class；檔＝動作） ==
// == 2026-09-27 自 tab_setup/view.php 分遷入（one file one action） ==

trait build_tab_setup_body
{
    public function build_tab_setup_body($project_row)
    {
        // 守衛：新建語境（無專案列）→ 檢查無路徑可查，回提示殼（走既有 sc_empty_state 共用區塊）
        $is_new_project = empty($project_row);
        if ($is_new_project) {
            return $this->build_notice_pane("請先儲存專案資料，再執行就緒檢查。");
        }
        
        // 檢查輸入（準確值——由專案列 declare）
        $project_path = $project_row["project_path"];
        $description  = $project_row["description"];

        $checklist_result = checks()->run_checklist($project_path, $description);

        $outputLines   = [];
        $outputLines[] = "<div class='modal_pane on' data-pane='setup'>";
            $outputLines[] = "<div class='setup'>";
                $outputLines[] = $this->render_setup_panel($checklist_result);
            $outputLines[] = "</div>";
        $outputLines[] = "</div>"; 
        
        return implode("\r\n", $outputLines);
    }
}
