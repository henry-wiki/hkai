<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form\action;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;
use function sc\app\hkai\plugins\sc_ai_projects\tab_general_form\repo_update;
use function sc\app\hkai\plugins\sc_ai_projects\tab_general_form\validator;

// == sc_ai_projects tab_general_form project_add：tab_general_form 域動作（trait——掛入 tab_general_form_Class；檔＝動作） ==
// == 2026-09-27 自 tab_general_form/handler.php 分遷入（one file one action） ==

trait project_add
{
    public function project_add()
    {
        $input_data = $this->read_input_data();

        // 表單資料（serialize 包——此層只讀 formdata）
        $form_data = isset($input_data["formdata"]) ? $input_data["formdata"] : [];

        // 解析欄位（project_path 先落——驗證與寫入共用）
        $project_path = sc_ai_projects()->project()->normalize_project_path($form_data["project_path"] ?? "");

        // 驗證（project_path 必填＋目錄存在）
        $validate_result = validator()->validate_project_input($project_path);
        if ($validate_result["ok"] === false) {
            return sc_response()->json($validate_result, 400);
        }

        // 重複檢查（同一路徑唔准兩個專案——DB 查現有 row）
        $conflicting_project_row = repo_select()->get_project_row_by_path($project_path);

        // 判定：路徑已有專案 → 拒絕建立（欄位錯誤——前端 apply_form_errors 定位 project_path）
        if ($conflicting_project_row !== null) {
            return sc_response()->json(["ok" => false, "errors" => ["project_path" => "此路徑已有專案「" . $conflicting_project_row["project_name"] . "」使用"]], 400);
        }

        // 解析欄位（已驗證——可直接取值）
        $project_name     = trim($form_data["project_name"] ?? "");

        // 判定：專案名未填 → 以路徑資料夾名兜底
        if ($project_name === "") {
            $project_name = basename($project_path);
        }
        $git_remote_url   = trim($form_data["git_remote_url"] ?? "");
        $description      = trim($form_data["description"] ?? "");

        // 專案 slug：不再由用戶定義——一律 proj-＋8 位短 UID（產生器內已確保未佔用）
        $project_slug = sc_ai_projects()->project()->generate_project_slug();

        // 判定：產生失敗（重試用盡）→ 回錯誤
        if ($project_slug === "") {
            return sc_response()->json(["ok" => false, "error" => "slug generation failed"], 500);
        }
        // 下一個排序索引（現有最大 + 1）
        $max_sort_row = sc_db()->selectOne("SELECT MAX(project_sort_index) AS max_sort_index FROM projects");

        // 判定：無資料列 → 從 0 起算
        $project_sort_index = 0;
        if ($max_sort_row !== null) {
            $project_sort_index = (int)$max_sort_row["max_sort_index"] + 1;
        }

        $now = date("Y-m-d H:i:s");

        $project_datapairs = [];
        $project_datapairs["project_slug"]       = $project_slug;
        $project_datapairs["project_name"]       = $project_name;
        $project_datapairs["project_path"]       = $project_path;
        $project_datapairs["git_remote_url"]     = $git_remote_url;
        $project_datapairs["description"]        = $description;
        $project_datapairs["provider_id"]        = "";
        $project_datapairs["model_id"]           = "";
        $project_datapairs["reasoning_effort"]   = "";
        $project_datapairs["tech_stack"]         = "";
        $project_datapairs["project_sort_index"] = $project_sort_index;
        $project_datapairs["project_created_at"] = $now;
        $project_datapairs["project_updated_at"] = $now;

        repo_update()->insert_project($project_datapairs);

        return sc_response()->json(["ok" => true, "project_slug" => $project_slug]);
    }
}
