<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\repo_select;

// == sc_ai_projects project get_project_row_by_path：project 域動作（trait——掛入 project_Class；檔＝動作：專案列讀取——by project_path） ==

trait get_project_row_by_path
{
    // 讀取專案列（依 project_path——建立／編輯重複檢查用；查無 → null）
    public function get_project_row_by_path($project_path)
    {
        return sc_db()->selectOne(
            "SELECT * FROM projects WHERE project_path = ?",
            [$project_path]
        );
    }
}
