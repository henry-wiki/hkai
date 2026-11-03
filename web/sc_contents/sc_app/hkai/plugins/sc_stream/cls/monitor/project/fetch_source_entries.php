<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\project;

// == project fetch_source_entries：取此 target 即時資料（trait——掛入 project_Class） ==
// == 上游：本檔 init_snapshot()／monitor()->get_target_source_entries() ==
// == 下游：即時資料來源（DB／檔案）——唔係讀 snapshot.json ==

trait fetch_source_entries
{
    // 取當刻 entries：{project_slug: {lastupdate, polling}}
    public function fetch_source_entries()
    {
        $project_map = [];


        $project_rows = sc_db()->connection("sqlite")->select(
            "SELECT project_id, project_slug, project_status, project_updated_at, chat_updated_at"
            . " FROM projects ORDER BY project_sort_index ASC"
        );

        // 各專案對話數（一次 GROUP BY——唔逐個 COUNT）
        $chat_count_rows = sc_db()->connection("sqlite")->select(
            "SELECT project_id, COUNT(*) AS chat_count FROM chats GROUP BY project_id"
        );

        $chat_count_map = [];

        foreach ($chat_count_rows as $chat_count_row) {
            $chat_count_project_id = (int)$chat_count_row["project_id"];
            $chat_count_map[$chat_count_project_id] = (int)$chat_count_row["chat_count"];
        }

        foreach ($project_rows as $project_row) {
            $project_slug   = (string)$project_row["project_slug"];
            $project_status = (string)$project_row["project_status"];

            $project_updated_at = (string)$project_row["project_updated_at"];
            $chat_updated_at    = (string)$project_row["chat_updated_at"];

            $project_item               = [];
            $project_item["status"]     = $project_status;
            $project_lastupdate      = max($project_updated_at, $chat_updated_at);

            $project_item["lastupdate"] = $project_lastupdate;

            $project_id         = (int)$project_row["project_id"];
            $project_chat_count = isset($chat_count_map[$project_id]) ? $chat_count_map[$project_id] : 0;

            $project_item["chat_count"] = $project_chat_count;

            $project_map[$project_slug] = $project_item;
        }

        ksort($project_map);

        return $project_map;
    }
}
