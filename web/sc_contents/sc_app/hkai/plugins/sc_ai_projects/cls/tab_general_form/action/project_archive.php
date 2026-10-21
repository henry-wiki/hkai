<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form\action;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;
use function sc\app\hkai\plugins\sc_ai_projects\tab_general_form\repo_update;

// == sc_ai_projects tab_general_form project_archive：tab_general_form 域動作（trait——掛入 tab_general_form_Class；檔＝動作） ==
// == 2026-10-04 由 project_delete 改歸檔（軟處理——project_status＝archived，唔刪任何資料） ==

trait project_archive
{
    public function project_archive()
    {
        $input_data = sc_request()->json();

        // 目標專案（只認 project_slug——前端帶；直接攞 row）
        $project_slug = trim($input_data["project_slug"] ?? "");

        // 判定：Home 專案（slug 空／home）→ 唔准歸檔
        if ($project_slug === "" || $project_slug === "home") {
            return sc_response()->json(["ok" => false, "error" => "home project cannot be modified"], 403);
        }

        $project_row  = repo_select()->get_project_row($project_slug);

        // 專案不存在 → 失敗
        if ($project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "project not found"], 404);
        }

        // 準確值：歸檔只收 project_id
        $project_id = $project_row["project_id"];

        // 歸檔（project_status＝archived——實作喺 repo_update）
        repo_update()->archive_project($project_id);

        return sc_response()->json(["ok" => true]);
    }
}
