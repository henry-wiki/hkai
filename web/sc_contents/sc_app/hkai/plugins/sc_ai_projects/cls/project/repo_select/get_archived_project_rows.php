<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\repo_select;

// == sc_ai_projects project get_archived_project_rows：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-10-04 已歸檔專案列讀取（get_project_rows 嘅鏡像——WHERE 反轉；清單歸檔語境用） ==

trait get_archived_project_rows
{
    // 讀取已歸檔專案列（project_status＝archived——依最近活動排序；對照 get_project_rows 而寫）
    public function get_archived_project_rows()
    {
        return sc_db()->select(
            "SELECT p.*, COALESCE(MAX(c.chat_updated_at), p.project_updated_at) AS project_last_activity_at "
            . "FROM projects p LEFT JOIN chats c ON c.project_id = p.project_id "
            . "WHERE p.project_status = 'archived' "
            . "GROUP BY p.project_id "
            . "ORDER BY project_last_activity_at DESC"
        );
    }
}
