<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\resolver;

// == sc_ai_projects project normalize_project_path：project 域動作（trait——掛入 project_Class；檔＝動作）
// == 職責：路徑統一（一律 "/"；去尾斜線、保留根磁碟斜線）

trait normalize_project_path
{
    // 統一專案路徑格式（"\" → "/"；去尾 "/"）
    public function normalize_project_path($project_path)
    {
        $project_path = trim((string)$project_path);
        $project_path = str_replace("\\", "/", $project_path);

        // 判定：長度大於 1 → 去尾斜線（"G:/" 保留）
        if (strlen($project_path) > 1) {
            $project_path = rtrim($project_path, "/");
        }

        return $project_path;
    }
}
