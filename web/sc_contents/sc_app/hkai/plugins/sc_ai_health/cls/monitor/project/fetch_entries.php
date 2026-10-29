<?php

namespace sc\app\hkai\plugins\sc_ai_health\monitor\project;

// == project fetch_entries：逐個專案最後改動＋進行中訊息數（trait——掛入 project_Class） ==
// == 上游：project_Class；下游：DB projects（時間）＋messages/polling（數 meta.json） ==

trait fetch_entries
{
    // 取當刻 entries：{project_slug: {lastupdate, polling}}
    public function fetch_entries()
    {
        $project_map = [];

        $messages_polling_dir = sc_get_config("hkai.messages_polling_dir");

        $project_rows = sc_db()->connection("sqlite")->select(
            "SELECT project_slug, project_status, project_updated_at, chat_updated_at"
            . " FROM projects ORDER BY project_sort_index ASC"
        );

        foreach ($project_rows as $project_row) {
            $project_slug   = (string)$project_row["project_slug"];
            $project_status = (string)$project_row["project_status"];

            $project_updated_at = (string)$project_row["project_updated_at"];
            $chat_updated_at    = (string)$project_row["chat_updated_at"];

            $polling_meta_files = glob($messages_polling_dir . "/" . $project_slug . "/*/*/meta.json");
            $polling_count      = is_array($polling_meta_files) ? count($polling_meta_files) : 0;

            $project_item               = [];
            $project_item["status"]     = $project_status;
            $project_item["lastupdate"] = max($project_updated_at, $chat_updated_at);
            $project_item["polling"]    = $polling_count;

            $project_map[$project_slug] = $project_item;
        }

        ksort($project_map);

        return $project_map;
    }
}
