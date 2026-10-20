<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form\view;

// == sc_ai_projects tab_general_form build_general_form_tab_foot_add：tab_general_form 域動作（trait——掛入 tab_general_form_Class；檔＝動作） ==
// == 2026-09-27 自 tab_general_form/view.php 分遷入（one file one action） ==

trait build_general_form_tab_foot_add
{
    public function build_general_form_tab_foot_add()
    {
        $button_lines = [];

        // 取消鈕（各 tab 皆有——關閉視窗 affordance）
        $cancel_wrapper   = [];
        $cancel_wrapper[] = "<div class='sc_btn_wrapper'>";
        $cancel_wrapper[] = "<button class='project_modal_btn btn_item' data-btn='project_cancel'>取消</button>";
        $cancel_wrapper[] = "</div>";
        $button_lines[]   = implode("\r\n", $cancel_wrapper);

        // 儲存鈕（新增——建立專案；data-btn=project_add → 交 project_add binder 送出）
        $save_wrapper   = [];
        $save_wrapper[] = "<div class='sc_btn_wrapper'>";
        $save_wrapper[] = "<button class='project_modal_btn primary btn_item' data-btn='project_add'>建立專案</button>";
        $save_wrapper[] = "</div>";
        $button_lines[] = implode("\r\n", $save_wrapper);

        return implode("\r\n", $button_lines);
    }
}
