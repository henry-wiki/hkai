<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_diagnostics\view;

// == sc_ai_projects tab_diagnostics build_tab_diagnostics_body：tab_diagnostics 域動作（trait——掛入 tab_diagnostics_Class；檔＝動作） ==

trait build_tab_diagnostics_body
{
    public function build_tab_diagnostics_body($project_row, $diagnostics_foot_html)
    {
        // 守衛：新建語境（無專案列）→ 無路徑可掃，回提示殼（同 git 域模式）
        $is_new_project = empty($project_row);
        if ($is_new_project) {
            return $this->build_notice_pane(hkai__i18n()->get("diagnostics.need_save"));
        }

        // 資料與渲染分家——只組外殼（每 tab 獨立：section 內容開面板只載首個、其餘點擊先 fetch——footer 由呼叫端帶入）
        $outputLines   = [];
        $outputLines[] = "<div class='modal_pane on' data-pane='diagnostics'>";
        $outputLines[] = "<div class='diagnostics' data-loaded='1'>";
        $outputLines[] = $this->render_diagnostics_result($project_row, $diagnostics_foot_html);
        $outputLines[] = "</div>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}
