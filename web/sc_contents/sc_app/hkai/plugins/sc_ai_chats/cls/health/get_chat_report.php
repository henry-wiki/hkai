<?php

namespace sc\app\hkai\plugins\sc_ai_chats\health;

// == sc_ai_chats health get_chat_report：health 域動作（trait——掛入 health_Class；檔＝動作） ==
// == 2026-09-28 health report（chat）——十一鍵（chat_id／chat_sid／project_id／chat_status／chat_segment／last_update／last_update_text／completed_count／failed_count／cancelled_count／polling_count） ==
// == 2026-09-28 強化：計數改讀 chats denormalized 欄（零 messages 掃描）＋當前專案過濾（request_data project_slug 查表） ==
// == 2026-10-04 清單語境：request_data chat_sid='archive' → 只供歸檔組；其餘 → 未歸檔組（health 一次一個清單） ==
// == 2026-10-04 加 chat_status 鍵（報告每行自帶狀態——前端 get or compare 有得對） ==

trait get_chat_report
{
    // ==== report ====
    // 讀取當前專案對話 health 報告（九鍵；id 轉 int、時間出 Unix 秒＋相對時間文——前端原樣套用；計數直接讀 chats 欄；請求資料＝health 原樣轉交——按需取用）
    public function get_chat_report($request_data)
    {
        // 當前專案（request_data project_slug 直接查表——解析即查表；request_data 由 health 原樣帶入；讀值防缺＝外部輸入）
        $project_slug = $request_data["project_slug"] ?? "";

        // 專案段查表用純 slug（歸檔語境形 archive@<project_slug> → @ 後；其餘 → 原值）
        $project_slug = sc_ai_projects()->project()->get_bare_project_slug($project_slug);

        $project_row  = sc_ai_projects()->project()->get_project_row($project_slug);

        $project_id = 0;
        // 判定：查有專案 → 取 id（查無 → 0＝冇對話命中）
        if ($project_row !== null) {
            $project_id = (int)$project_row["project_id"];
        }

        // 清單語境（request_data chat_sid 原值——歸檔語境（archive／archive@<chat_sid>）＝歸檔清單；URL 段原封照帶交後端判斷）
        $chat_sid = $request_data["chat_sid"] ?? "";

        // 語境判定：歸檔語境 → 歸檔組；其餘 → 未歸檔組（health 一次只供一個清單；判定位喺 is_archive_chat_segment 唯一住處）
        $report_chat_status = "normal";
        if (sc_ai_chats()->chat()->is_archive_chat_segment($chat_sid)) {
            $report_chat_status = "archived";
        }

        $chat_report_rows = sc_db()->select("SELECT chat_id, chat_sid, project_id, chat_status, chat_updated_at, completed_msg, failed_msg, cancelled_msg, polling_msg FROM chats WHERE project_id = ? AND chat_status = ?", [$project_id, $report_chat_status]);

        $chat_reports = [];

        // 逐對話合成報告（時間同 render_chat_item 同源；計數直接讀 chats 欄——零 messages 掃描）
        foreach ($chat_report_rows as $chat_report_row_item) {
            $chat_reports[] = [
                "chat_id"          => (int)$chat_report_row_item["chat_id"],
                "chat_sid"         => $chat_report_row_item["chat_sid"],
                "project_id"       => (int)$chat_report_row_item["project_id"],
                "chat_status"      => $chat_report_row_item["chat_status"],
                "chat_segment"     => sc_ai_chats()->chat()->build_chat_segment($chat_report_row_item["chat_sid"], $chat_report_row_item["chat_status"]),
                "last_update"      => date("Y-m-d H:i:s", strtotime($chat_report_row_item["chat_updated_at"])),
                "last_update_text" => sc_format_relative_time($chat_report_row_item["chat_updated_at"]),
                "completed_count"  => (int)$chat_report_row_item["completed_msg"],
                "failed_count"     => (int)$chat_report_row_item["failed_msg"],
                "cancelled_count"  => (int)$chat_report_row_item["cancelled_msg"],
                "polling_count"    => (int)$chat_report_row_item["polling_msg"],
            ];
        }

        return $chat_reports;
    }
}
