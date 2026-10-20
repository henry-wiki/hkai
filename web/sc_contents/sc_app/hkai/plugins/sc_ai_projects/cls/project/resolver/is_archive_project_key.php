<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\resolver;

// == sc_ai_projects project is_archive_project_key：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-10-04 專案段歸檔語境判定（archive＝已歸檔專案清單——解析規則唯一住處；對照 is_archive_chat_segment 而寫） ==
// == 2026-10-07 加語境形 archive@<project_slug>（歸檔語境瀏覽單一歸檔專案——同 chat 段同一語言） ==

trait is_archive_project_key
{
    // 判定專案段係唔係歸檔語境（純關鍵字 archive，或語境形 archive@<project_slug>；解析規則唯一住處）
    public function is_archive_project_key($project_key)
    {
        // 純關鍵字（歸檔清單本身——/archive/）
        if ($project_key === "archive") {
            return true;
        }

        // 判定：語境形（archive@<project_slug>——前＝關鍵字、後＝專案；第一個 @ 分界）
        if (strpos($project_key, "archive@") === 0) {
            return true;
        }

        return false;
    }
}
