<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\repo_update;

// == sc_ai_projects project restore_project：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-10-04 還原專案（project_status＝active——歸檔嘅鏡像；對照 archive_project 而寫） ==

trait restore_project
{
    // 還原專案（projects project_status＝active——唔碰對話／訊息／檔案／時間）
    public function restore_project($project_id)
    {
        $connection = sc_db()->connection("sqlite");

        $connection->statement(
            "UPDATE projects SET project_status = 'active' WHERE project_id = ?",
            [$project_id]
        );
    }

}
