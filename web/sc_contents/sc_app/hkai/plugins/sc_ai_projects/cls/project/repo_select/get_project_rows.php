<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\repo_select;

use function sc\app\hkai\plugins\sc_ai_projects\init;

// == sc_ai_projects project get_project_rows：project 域動作（trait——掛入 project_Class；檔＝動作：專案列讀取——單條／全部） ==
// == 2026-09-29 兩支讀取合併一支（原 get_project_row／get_project_rows——2026-09-27 自分遷入收返） ==

trait get_project_rows
{
    // 讀取單一專案列（依 id 或 slug——由 init 初始資料按 id／slug 兩表查）
    public function get_project_row($needle)
    {
        $needle = $needle ?? "";
        $needle = trim($needle);

        // 判定：無值 → 回 null
        if (!$needle) {
            return null;
        }

        // 判定：純數字 → 按 id 查
        if (ctype_digit($needle)) {
            $project_rows_by_id = init()->get_initial_data("project_rows_by_id");

            return $project_rows_by_id[$needle] ?? null;
        }

        $project_rows_by_slug = init()->get_initial_data("project_rows_by_slug");

        return $project_rows_by_slug[$needle] ?? null;
    }

    // 讀取專案列（全部——依最近活動排序）
    public function get_project_rows()
    {
        // 依最近活動排序（最新對話更新時間優先——同卡顯示／health report 同一來源；冇對話退專案自身更新）
        // 歸檔排除（project_status＝archived——已歸檔專案唔出清單）
        return sc_db()->select(
            "SELECT p.*, COALESCE(MAX(c.chat_updated_at), p.project_updated_at) AS project_last_activity_at "
            . "FROM projects p LEFT JOIN chats c ON c.project_id = p.project_id "
            . "WHERE p.project_status != 'archived' "
            . "GROUP BY p.project_id "
            . "ORDER BY project_last_activity_at DESC"
        );
    }
}
