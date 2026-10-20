<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\view;

// == sc_ai_projects tab_git build_tab_git_body：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/view.php 分遷入（one file one action） ==

trait build_tab_git_body
{
    public function build_tab_git_body($project_row)
    {
        // 守衛：新建語境（無專案列）→ 無路徑可查 git，回提示殼（同 setup 域模式）
        $is_new_project = empty($project_row);
        if ($is_new_project) {
            return $this->build_notice_pane(hkai__i18n()->get("git.need_save"));
        }

        // 準確值：診斷只收 project_path
        $project_path = $project_row["project_path"];

        // 資料與渲染分家——先取 git 狀態（唯讀診斷），再由本 view 純渲染
        $git_status = sc_ai_diagnostics()->git()->run_git_status($project_path);

        $outputLines   = [];
        $outputLines[] = "<div class='modal_pane on' data-pane='git'>";
        $outputLines[] = "<div class='git' data-loaded='1'>";
        $outputLines[] = $this->render_git_status($git_status);
        $outputLines[] = "</div>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}
