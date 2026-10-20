<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form\validate;

// == sc_ai_projects tab_general_form validate_project_input：tab_general_form 域動作（trait——掛入 tab_general_form_Class；檔＝動作） ==
// == 2026-09-27 自 tab_general_form/validator.php 分遷入（one file one action） ==

trait validate_project_input
{
    public function validate_project_input($project_path)
    {
        $errors = [];

        // project_path 未填
        if ($project_path === "") {
            $errors["project_path"] = "請填寫專案路徑";
        }

        // project_path 已填 → 檢查目錄存在
        if ($project_path !== "") {
            if (!is_dir($project_path)) {
                $errors["project_path"] = "專案路徑不存在或不是目錄";
            }
        }

        // 有錯誤 → 回傳錯誤清單
        if (count($errors) > 0) {
            return ["ok" => false, "errors" => $errors];
        }

        return ["ok" => true];
    }
}
