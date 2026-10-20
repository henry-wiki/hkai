<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\resolver;

// == sc_ai_projects project get_bare_project_slug：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-10-07 純 project_slug 抽取（語境形 keyword@<project_slug> → @ 後；無 @ → 原值——第一個 @ 分界；對照 get_bare_chat_sid 而寫） ==

trait get_bare_project_slug
{

    // 由專案段取純 project_slug（查表／操作一律用純 slug；語境由語境判定函數負責）
    public function get_bare_project_slug($project_key)
    {
        // 無 @ → 純 slug 原值
        if (strpos($project_key, "@") === false) {
            return $project_key;
        }

        // 語境形 → @ 後（第一個 @ 分界）
        return substr($project_key, strpos($project_key, "@") + 1);
    }

}
