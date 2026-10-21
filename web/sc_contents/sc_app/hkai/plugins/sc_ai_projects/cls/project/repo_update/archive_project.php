<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\repo_update;

// == sc_ai_projects project archive_project：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-10-04 由 delete_project 改歸檔（軟處理——唔刪任何資料） ==

trait archive_project
{
    // 歸檔專案（projects project_status＝archived——唔碰對話／訊息／檔案／時間）
    public function archive_project($project_id)
    {
        // 判定：Home 專案（slug＝home）→ 拒絕（用戶不可改）
        $target_project_row = sc_db()->selectOne("SELECT project_slug FROM projects WHERE project_id = ?", [(int)$project_id]);

        // 判定：Home → 唔准改
        if ($target_project_row !== null && $target_project_row["project_slug"] === "home") {
            return false;
        }

        $connection = sc_db()->connection("sqlite");

        $connection->statement(
            "UPDATE projects SET project_status = 'archived' WHERE project_id = ?",
            [$project_id]
        );
    }

}
