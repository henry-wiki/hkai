<?php

namespace sc\app\hkai\plugins\sc_ai_projects\health;

// == sc_ai_projects health get_project_report：health 域動作（trait——掛入 health_Class；檔＝動作） ==
// == 2026-09-28 health report（project）——只取指定欄（project_id／project_slug／project_status／last_update） ==
// == 2026-10-07 卡片身份段：出 project_segment（段形——歸檔＝archive@{slug}；前端比對同一語言） ==
// == 2026-10-07 清單語境：request_data project_slug='archive' → 只供歸檔組；其餘 → 未歸檔組（health 一次一個清單——對照 get_chat_report 而寫） ==

trait get_project_report
{
    // ==== report ====
    // 讀取當前清單語境專案 health 報告（八鍵；id 轉 int、時間出 Y-m-d H:i:s＋相對時間文——前端原樣套用；計數由 chats 表彙總；請求資料＝health 原樣轉交——按需取用）
    public function get_project_report($request_data)
    {
        // 清單語境（request_data project_slug 原值——歸檔語境（archive）＝歸檔清單；URL 段原封照帶交後端判斷）
        $project_slug = $request_data["project_slug"] ?? "";

        // 語境判定：歸檔語境 → 歸檔組；其餘 → 未歸檔組（health 一次只供一個清單；判定位喺 is_archive_project_key 唯一住處）
        $report_project_condition = "p.project_status != 'archived'";
        if (sc_ai_projects()->project()->is_archive_project_key($project_slug)) {
            $report_project_condition = "p.project_status = 'archived'";
        }

        // 專案列＋對話彙總（LEFT JOIN——冇對話嘅專案都要出行；狀態條件跟清單語境）
        $project_report_rows = sc_db()->select(
            "SELECT p.project_id, p.project_slug, p.project_status, p.project_updated_at, "
            . "COUNT(c.chat_id) AS chat_count, COALESCE(SUM(c.polling_msg), 0) AS polling_count, "
            . "MAX(c.chat_updated_at) AS latest_chat_update "
            . "FROM projects p LEFT JOIN chats c ON c.project_id = p.project_id "
            . "WHERE " . $report_project_condition . " "
            . "GROUP BY p.project_id, p.project_slug, p.project_status, p.project_updated_at"
        );

        $project_reports = [];

        // 逐列整形（id 轉 int；更新時間＝最近對話更新，冇對話退專案自身更新——顯示與 attr 同一來源）
        foreach ($project_report_rows as $project_report_row_item) {
            $project_last_update = $project_report_row_item["latest_chat_update"];

            // 判定：冇對話更新 → 退用專案自身更新時間
            if ($project_last_update === null) {
                $project_last_update = $project_report_row_item["project_updated_at"];
            }

            $project_reports[] = [
                "project_id"       => (int)$project_report_row_item["project_id"],
                "project_slug"     => $project_report_row_item["project_slug"],
                "project_status"   => $project_report_row_item["project_status"],
                "project_segment"  => sc_ai_projects()->project()->build_project_segment($project_report_row_item["project_slug"], $project_report_row_item["project_status"]),
                "last_update"      => date("Y-m-d H:i:s", strtotime($project_last_update)),
                "last_update_text" => sc_format_relative_time($project_last_update),
                "chat_count"       => (int)$project_report_row_item["chat_count"],
                "polling_count"    => (int)$project_report_row_item["polling_count"],
            ];
        }

        return $project_reports;
    }
}
