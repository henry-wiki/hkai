<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\slug;

// == sc_ai_projects project generate_project_slug：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-10-07 立：slug 不再由用戶定義——一律 proj-＋8 位短 UID（短 UID 本體＝框架 sc_generate_short_uid） ==

trait generate_project_slug
{
    public function generate_project_slug()
    {
        // 產生至未佔用為止（重試上限 5 次——36^8 容量下幾乎不會發生；短 UID 交框架 sc_generate_short_uid——slug 用 8 位）
        for ($attempt_idx = 0; $attempt_idx < 3; $attempt_idx++) {
            $project_slug = "proj-" . sc_generate_short_uid(8);
            
            // 未佔用 → 採用
            if ($this->is_slug_taken($project_slug, 0) === false) {
                return $project_slug;
            }
        }
        
        return "";
    }
}
