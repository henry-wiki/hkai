<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_diagnostics\view;

// == sc_ai_projects tab_diagnostics render_diagnostics_result：tab_diagnostics 域動作（trait——掛入 tab_diagnostics_Class；檔＝動作） ==
// == 2026-10-04 重寫（左側 tab 化——nav 欄＋內容欄；每 tab 獨立：開面板只載首個 section、其餘點擊先 fetch） ==

trait render_diagnostics_result
{
    public function render_diagnostics_result($project_row, $diagnostics_foot_html)
    {
        // 診斷 section 清單（單一來源——左側 tab 由此生成；initial data）
        $diagnostics_sections = $this->get_initial_data("diagnostics_sections");

        // 首個 section id（開面板時先載佢——只跑佢自己嗰份掃描）
        $section_ids      = array_keys($diagnostics_sections);
        $first_section_id = $section_ids[0];

        $outputLines   = [];
        $outputLines[] = "<div class='diagnostics_pane'>";
            $outputLines[] = "<div class='modal__navigation'>";
                $outputLines[] = "<div class='modal__navigation-content'>";

                // 首項標記（預設 active）
                $is_first_section = true;

                // 逐個 section 生成左側按鈕
                foreach ($diagnostics_sections as $diagnostics_item) {
                    $outputLines[] = $this->build_diagnostics_nav_button($diagnostics_item, $is_first_section);
                    $is_first_section = false;
                }

                $outputLines[] = "</div>";
            $outputLines[] = "</div>";
            $outputLines[] = "<div class='modal__content'>";
                $outputLines[] = "<div class='modal__content-main'>";
                    $outputLines[] = $this->build_diagnostics_section($project_row, $first_section_id);
                $outputLines[] = "</div>";
                $outputLines[] = $diagnostics_foot_html;
            $outputLines[] = "</div>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}
