<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\action;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;
use function sc\app\hkai\plugins\sc_ai_projects\tab_general_form\repo_update;

// == sc_ai_projects project ensure_home_project：project 域動作（trait——掛入 project_Class；檔＝動作）
// == 職責：確保 Home 專案列存在（project_slug 固定 "home"·路徑＝<storage>/playground；不可更新／刪除）

trait ensure_home_project
{
    // 確保 Home 專案列存在（slug＝home、路徑＝playground；缺就建立／舊空 slug 自動歸位）
    public function ensure_home_project()
    {
        $playground_path = hkai__core()->get_storage_dir() . "/playground";

        // 判定：沙盒目錄唔存在 → 建立
        if (!is_dir($playground_path)) {
            @mkdir($playground_path, 0777, true);
        }

        $home_project_row = repo_select()->get_project_row("home");

        // 判定：Home 已存在 → 唔做（名稱／路徑由系統建立時決定，之後唔改）
        if ($home_project_row !== null) {
            return;
        }

        // 只填核心欄位（其餘靠 DB default：provider/model/reasoning/tech/git/description/時間）
        $project_datapairs = [];
        $project_datapairs["project_slug"]       = "home";
        $project_datapairs["project_name"]       = "Home";
        $project_datapairs["project_path"]       = $playground_path;
        $project_datapairs["project_sort_index"] = 1;

        repo_update()->insert_project($project_datapairs);
    }
}
