<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form\view;

// == sc_ai_projects tab_general_form build_general_form_tab_foot_edit：tab_general_form 域動作（trait——掛入 tab_general_form_Class；檔＝動作） ==
// == 2026-09-27 自 web/sc_contents/sc_app/hkai/plugins/sc_ai_settings/cls/view/view.php 分遷入（one file one action） ==

trait build_general_form_tab_foot_edit
{
    public function build_general_form_tab_foot_edit($project_slug = "")
    {
        $button_lines = [];

        // 判定：Home 專案（slug 空／預設專案）→ 儲存鈕停用
        $is_home_project = ($project_slug === "");

        // 判定：slug 等於預設專案 → 同樣當 Home
        if ($project_slug === sc_get_config("hkai.default_project_slug")) {
            $is_home_project = true;
        }

        // 取消鈕（各 tab 皆有——關閉視窗 affordance）
        $cancel_wrapper   = [];
        $cancel_wrapper[] = "<div class='sc_btn_wrapper'>";
        $cancel_wrapper[] = "<button class='project_modal_btn btn_item' data-btn='project_cancel'>取消</button>";
        $cancel_wrapper[] = "</div>";
        $button_lines[]   = implode("\r\n", $cancel_wrapper);

        // 儲存鈕（編輯——儲存；data-btn=project_edit → 交 project_edit binder 送出）
        $save_wrapper   = [];
        $save_wrapper[] = "<div class='sc_btn_wrapper'>";
        // 判定：Home 專案 → 停用儲存（title 說明原因）
        if ($is_home_project === true) {
            $save_wrapper[] = "<button class='project_modal_btn primary btn_item' data-btn='project_edit' disabled title='" . hkai()->i18n()->get("project.home_locked") . "'>儲存</button>";
        } else {
            $save_wrapper[] = "<button class='project_modal_btn primary btn_item' data-btn='project_edit'>儲存</button>";
        }
        $save_wrapper[] = "</div>";
        $button_lines[] = implode("\r\n", $save_wrapper);

        return implode("\r\n", $button_lines);
    }
}
