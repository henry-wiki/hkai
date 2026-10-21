<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form\action;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;
use function sc\app\hkai\plugins\sc_ai_projects\tab_general_form\repo_update;
use function sc\app\hkai\plugins\sc_ai_projects\tab_general_form\validator;

// == sc_ai_projects tab_general_form project_edit：tab_general_form 域動作（trait——掛入 tab_general_form_Class；檔＝動作） ==
// == 2026-09-27 自 tab_general_form/handler.php 分遷入（one file one action） ==

trait project_edit
{
    public function project_edit()
    {
        $input_data = $this->read_input_data();

        // 表單資料（serialize 包——此層只讀 formdata）
        $form_data = isset($input_data["formdata"]) ? $input_data["formdata"] : [];

        // 編輯識別（頂層 project_slug——modal 狀態；直接攞 row）
        $project_slug = trim($input_data["project_slug"] ?? "");

        // 判定：Home 專案（slug 空／home）→ 唔准改
        if ($project_slug === "" || $project_slug === "home") {
            return sc_response()->json(["ok" => false, "error" => "home project cannot be modified"], 403);
        }
        $project_row  = repo_select()->get_project_row($project_slug);

        // 專案不存在 → 失敗
        if ($project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "project not found"], 404);
        }

        // 準確值：後續寫入只收 project_id
        $project_id = $project_row["project_id"];

        // 解析欄位（project_path 先落——驗證與寫入共用）
        $project_path = sc_ai_projects()->project()->normalize_project_path($form_data["project_path"] ?? "");

        // 驗證（project_path 必填＋目錄存在）
        $validate_result = validator()->validate_project_input($project_path);
        if ($validate_result["ok"] === false) {
            return sc_response()->json($validate_result, 400);
        }

        // 重複檢查（同一路徑唔准兩個專案——DB 查現有 row）
        $conflicting_project_row = repo_select()->get_project_row_by_path($project_path);

        // 判定：路徑已被佔用 → 檢查佔用者係唔係自己
        if ($conflicting_project_row !== null) {
            // 判定：佔用者唔係自己 → 拒絕儲存
            if ((int)$conflicting_project_row["project_id"] !== (int)$project_id) {
                return sc_response()->json(["ok" => false, "errors" => ["project_path" => "此路徑已有專案「" . $conflicting_project_row["project_name"] . "」使用"]], 400);
            }
        }

        // 解析欄位（已驗證——可直接取值）
        $project_name     = trim($form_data["project_name"] ?? "");

        // 判定：專案名未填 → 以路徑資料夾名兜底
        if ($project_name === "") {
            $project_name = basename($project_path);
        }
        $git_remote_url   = trim($form_data["git_remote_url"] ?? "");
        $description      = trim($form_data["description"] ?? "");

        // 專案 slug：不再由用戶定義——編輯唔改 slug（建立後固定）
        $project_datapairs = [];
        $project_datapairs["project_name"]       = $project_name;
        $project_datapairs["project_path"]       = $project_path;
        $project_datapairs["git_remote_url"]     = $git_remote_url;
        $project_datapairs["description"]        = $description;
        $project_datapairs["project_updated_at"] = date("Y-m-d H:i:s");

        repo_update()->update_project($project_id, $project_datapairs);

        return sc_response()->json(["ok" => true]);
    }
}
