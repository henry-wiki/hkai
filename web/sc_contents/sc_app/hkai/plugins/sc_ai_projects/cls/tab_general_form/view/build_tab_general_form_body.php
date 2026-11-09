<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form\view;

// == sc_ai_projects tab_general_form build_tab_general_form_body：tab_general_form 域動作（trait——掛入 tab_general_form_Class；檔＝動作） ==
// == 2026-09-27 自 web/sc_contents/sc_app/hkai/plugins/sc_ai_settings/cls/view/view.php 分遷入（one file one action） ==

trait build_tab_general_form_body
{
    public function build_tab_general_form_body($project_row)
    {
        // 從 $project_row 取出所有欄位（一眼知道下面會引用什麼）
        $project_name = $project_row["project_name"] ?? "";
        $project_path = $project_row["project_path"] ?? "";

        // 判定：歸檔專案 → 全部欄位唯讀（只能還原，唔可以改）
        $is_archived_project = (($project_row["project_status"] ?? "") === "archived");

        // 欄位選項（逐行賦值——一眼知道每個欄位的設定）
        $options_project_name = [];
        $options_project_name["placeholder"] = hkai()->i18n()->get("project.form.name_placeholder");
        $options_project_name["subtitle"] = hkai()->i18n()->get("project.form.name_subtitle");
        $project_name_subtitle = $options_project_name["subtitle"];

        $options_project_path = [];
        $options_project_path["required"]    = true;
        $options_project_path["placeholder"] = "/home/user/projects/my-app";
        $options_project_path["subtitle"]    = hkai()->i18n()->get("project.form.path_subtitle");
        $project_path_subtitle = $options_project_path["subtitle"];

        // 判定：歸檔專案 → 兩個欄位都唯讀
        if ($is_archived_project === true) {
            $options_project_name["readonly"] = true;
            $options_project_path["readonly"] = true;
        }

        $outputLines = [];
        $outputLines[] = "<div class='modal_pane on' data-pane='general_form'>";
        $outputLines[] = "<div class='modal__content-main modal-form'>";

            // 區塊 1：專案資料
            $outputLines[] = "<div class='project-section'>";
            $outputLines[] = $this->build_section_heading(hkai()->i18n()->get("project.form.section_basic"), hkai()->i18n()->get("project.form.section_basic_desc"));

                // 專案名稱（選填——不填自動以路徑資料夾名）
                $outputLines[] = "<div class='modal-form__row form_row'>";
                $outputLines[] = "<div class='modal-form__icon'></div>";
                $outputLines[] = "<div class='modal-form__info'>";
                $outputLines[] = "<div class='modal-form__label'>" . hkai()->i18n()->get("project.form.name_label") . "</div>";
                $outputLines[] = "<div class='modal-form__desc'>" . $project_name_subtitle . "</div>";
                $outputLines[] = "</div>";
                $outputLines[] = "<div class='modal-form__content'>";
                $outputLines[] = $this->build_field_row("project_name", $project_name, $options_project_name);
                $outputLines[] = "<div class='form_err'></div>";
                $outputLines[] = "</div>";
                $outputLines[] = "</div>";

                // 專案路徑（必填）
                $outputLines[] = "<div class='modal-form__row form_row'>";
                $outputLines[] = "<div class='modal-form__icon'></div>";
                $outputLines[] = "<div class='modal-form__info'>";
                $outputLines[] = "<div class='modal-form__label'>" . hkai()->i18n()->get("project.form.path_label") . "<span class='form_required'>*</span></div>";
                $outputLines[] = "<div class='modal-form__desc'>" . $project_path_subtitle . "</div>";
                $outputLines[] = "</div>";
                $outputLines[] = "<div class='modal-form__content'>";
                $outputLines[] = $this->build_field_row("project_path", $project_path, $options_project_path);
                $outputLines[] = "<div class='form_err'></div>";
                $outputLines[] = "</div>";
                $outputLines[] = "</div>";

            $outputLines[] = "</div>";

            // 區塊 2：補充資料
            $outputLines[] = "<div class='project-section'>";
            $outputLines[] = $this->build_section_heading(hkai()->i18n()->get("project.form.section_extra"), hkai()->i18n()->get("project.form.section_extra_desc"));
            // 歸檔專案（僅編輯──帶確認訊息）
            $outputLines[] = $this->build_remove_row($project_row);
            $outputLines[] = "</div>";

        $outputLines[] = "</div>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}
