<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form\action;

use function sc\app\hkai\plugins\sc_ai_projects\tab_general_form\validator;

// == sc_ai_projects tab_general_form verify_project_data：tab_general_form 域動作（trait——掛入 tab_general_form_Class；檔＝動作） ==
// == 2026-09-27 自 tab_general_form/handler.php 分遷入（one file one action） ==

trait verify_project_data
{
    public function verify_project_data()
    {
        $input_data = $this->read_input_data();

        // 表單資料（serialize 包——此層只讀 formdata）
        $form_data = isset($input_data["formdata"]) ? $input_data["formdata"] : [];

        // 解析欄位（project_path 先落——驗證）
        $project_path = trim($form_data["project_path"] ?? "");

        // 全表單驗證（project_path——與儲存同一套）
        $validate_result = validator()->validate_project_input($project_path);

        $errors = [];

        // 基本驗證失敗 → 收集錯誤
        if ($validate_result["ok"] === false) {
            $errors = $validate_result["errors"];
        }

        $result = [];
        $result["ok"]     = (count($errors) === 0);
        $result["errors"] = $errors;

        return sc_response()->json($result);
    }
}
