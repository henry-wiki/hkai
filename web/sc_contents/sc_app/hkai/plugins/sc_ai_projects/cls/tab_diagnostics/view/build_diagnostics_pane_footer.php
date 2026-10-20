<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_diagnostics\view;

// == sc_ai_projects tab_diagnostics build_diagnostics_pane_footer：tab_diagnostics 域動作（trait——掛入 tab_diagnostics_Class；檔＝動作） ==
// == 2026-10-04 立（左側 tab 化——內容欄底 footer；形跟 sc_ai_settings .modal__content-footer） ==

trait build_diagnostics_pane_footer
{
    // 內容欄底 footer（左＝本 pane 重新整理鈕（只重載當前 section——每 tab 獨立）；右＝本 tab 取消鈕；掛入 pane 內容欄尾）
    public function build_diagnostics_pane_footer()
    {
        // 重新整理鈕（本 pane 專用——每 tab 獨立：只重載當前 section）
        $foot_left_html        = "<button class='project_modal_btn btn_item' data-btn='diagnostics_refresh'>重新整理</button>";
        $diagnostics_foot_html = $this->build_tab_diagnostics_foot();

        $outputLines   = [];
        $outputLines[] = "<div class='modal__content-footer'>";
            $outputLines[] = "<div class='project_modal__footer'>";
                $outputLines[] = "<div class='project_modal__footer_left'>";
                    $outputLines[] = $foot_left_html;
                $outputLines[] = "</div>";
                $outputLines[] = "<div class='project_modal__footer_right'>";
                    $outputLines[] = $diagnostics_foot_html;
                $outputLines[] = "</div>";
            $outputLines[] = "</div>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}
