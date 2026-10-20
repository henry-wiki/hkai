<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form\action;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;
use function sc\app\hkai\plugins\sc_ai_projects\tab_general_form\repo_update;

// == sc_ai_projects tab_general_form project_delete：tab_general_form 域動作（trait——掛入 tab_general_form_Class；檔＝動作） ==
// == 2026-09-27 自 tab_general_form/handler.php 分遷入（one file one action） ==

trait project_delete
{
    public function project_delete()
    {
        $input_data = sc_request()->json();

        // 目標專案（只認 project_slug——前端帶；直接攞 row）
        $project_slug = trim($input_data["project_slug"] ?? "");
        $project_row  = repo_select()->get_project_row($project_slug);

        // 專案不存在 → 失敗
        if ($project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "project not found"], 404);
        }

        // 準確值：刪除只收 project_id
        $project_id = $project_row["project_id"];

        // 連帶刪除（明細＋主檔——實作喺 repo_update）
        repo_update()->delete_project($project_id);

        return sc_response()->json(["ok" => true]);
    }
}
