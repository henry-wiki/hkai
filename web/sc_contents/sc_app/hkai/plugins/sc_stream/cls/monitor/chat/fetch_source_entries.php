<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\chat;

// == chat fetch_source_entries：取此 target 即時資料（trait——掛入 chat_Class） ==
// == 上游：本檔 init_snapshot()／monitor()->get_target_source_entries() ==
// == 下游：即時資料來源（DB／檔案）——唔係讀 snapshot.json ==

trait fetch_source_entries
{
    // 取當刻 entries：{project_slug: {chat_sid: {status, lastupdate, polling}}}（按更新時間新→舊，最多 100 條）
    public function fetch_source_entries()
    {
        $chat_map = [];


        $chat_rows = sc_db()->connection("sqlite")->select(
            "SELECT c.chat_sid, c.chat_status, c.chat_updated_at, c.chat_message_count, p.project_slug"
            . " FROM chats c LEFT JOIN projects p ON p.project_id = c.project_id"
            . " ORDER BY c.chat_updated_at DESC LIMIT 100"
        );

        foreach ($chat_rows as $chat_row) {
            $chat_sid    = (string)$chat_row["chat_sid"];
            $chat_status = (string)$chat_row["chat_status"];

            $project_slug = (string)$chat_row["project_slug"];

            $chat_updated_at = (string)$chat_row["chat_updated_at"];

            $chat_lastupdate      = (string)$chat_updated_at;

            $chat_item                 = [];
            $chat_item["status"]       = $chat_status;
            $chat_item["lastupdate"]   = $chat_lastupdate;
            $chat_item["msg_count"]    = (int)$chat_row["chat_message_count"];

            // 兩層：先專案、後對話
            $chat_map[$project_slug][$chat_sid] = $chat_item;
        }

        return $chat_map;
    }
}
