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

        // 空 slug → 反映現實：冇指定就係 null（Home 屬呼叫端自己決定 fallback）
        if ($needle === "") {
            return null;
        }

        // home 段 → 查 Home 專案列（slug 固定 "home"；唔經快取）
        if ($needle === "home") {
            return sc_db()->selectOne("SELECT * FROM projects WHERE project_slug = 'home'");
        }

        // 判定：純數字 → 按 id 查
        if (ctype_digit($needle)) {
            $project_rows_by_id = init()->get_initial_data("project_rows_by_id");

            return $project_rows_by_id[$needle] ?? null;
        }

        $project_rows_by_slug = init()->get_initial_data("project_rows_by_slug");

        return $project_rows_by_slug[$needle] ?? null;
    }

    // 讀取專案列（Home 第一＋其餘 active 專案——依最近活動排序）
    public function get_project_rows()
    {
        // 第一支：Home（project_slug＝home）
        $home_project_rows = sc_db()->select("SELECT * FROM projects WHERE project_slug = 'home'");

        // 第二支：其餘專案（project_status＝active；home 排除；最近對話更新優先）
        $active_project_rows = sc_db()->select(
            "SELECT * FROM projects "
            . "WHERE project_status = 'active' "
            . "AND project_slug != 'home' "
            . "ORDER BY chat_updated_at DESC, project_updated_at DESC"
        );

        // Home 第一，其餘接住
        return array_merge($home_project_rows, $active_project_rows);
    }
}
