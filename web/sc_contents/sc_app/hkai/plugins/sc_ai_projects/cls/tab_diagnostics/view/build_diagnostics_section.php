<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_diagnostics\view;

// == sc_ai_projects tab_diagnostics build_diagnostics_section：tab_diagnostics 域動作（trait——掛入 tab_diagnostics_Class；檔＝動作） ==
// == 2026-10-04 立（每 tab 獨立——單一 section 外框＋內容：自己嗰份掃描→自己嗰個 renderer） ==

trait build_diagnostics_section
{
    // 單一 section（外框＋內容——開面板首載同點擊 fetch 共用同一支）
    public function build_diagnostics_section($project_row, $section_id)
    {
        $outputLines   = [];
        $outputLines[] = "<div class='diagnostics_section on' data-section='" . $section_id . "'>";
            $outputLines[] = $this->render_diagnostics_section($project_row, $section_id);
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }

    // section 內容（依 section 分派：自己嗰份掃描 → 自己嗰個 renderer）
    public function render_diagnostics_section($project_row, $section_id)
    {
        // 準確值：掃描只收 project_path
        $project_path = $project_row["project_path"];

        // 依 section 分派：每個 section 走自己嗰份掃描＋renderer（id 已由呼叫端對清單）
        switch ($section_id) {
            // General＝系統檢查掃描
            case "general":
                $git_status_result = sc_ai_diagnostics()->git()->run_git_status($project_path);
                return $this->render_general_result($git_status_result);
            // 檔案路徑＝超長路徑掃描
            case "file_path":
                $file_path_result = sc_ai_diagnostics()->files()->run_get_long_paths($project_path);
                return $this->render_file_path_result($file_path_result);
            // Git＝設定清單掃描
            case "git":
                $git_config_result = sc_ai_diagnostics()->git()->run_get_config_list($project_path);
                return $this->render_git_config_result($git_config_result);
        }
    }
}
