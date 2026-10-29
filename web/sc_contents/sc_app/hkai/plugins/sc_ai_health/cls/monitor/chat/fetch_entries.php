<?php

namespace sc\app\hkai\plugins\sc_ai_health\monitor\chat;

// == chat fetch_entries：最近 100 個對話的最後改動＋進行中訊息數（trait——掛入 chat_Class） ==
// == 上游：chat_Class；下游：DB chats（時間）＋messages/polling（數 meta.json） ==

trait fetch_entries
{
    // 取當刻 entries：{chat_sid: {status, lastupdate, polling}}（按更新時間新→舊，最多 100 條）
    public function fetch_entries()
    {
        $chat_map = [];

        $messages_polling_dir = sc_get_config("hkai.messages_polling_dir");

        $chat_rows = sc_db()->connection("sqlite")->select(
            "SELECT c.chat_sid, c.chat_status, c.chat_updated_at, p.project_slug"
            . " FROM chats c LEFT JOIN projects p ON p.project_id = c.project_id"
            . " ORDER BY c.chat_updated_at DESC LIMIT 100"
        );

        foreach ($chat_rows as $chat_row) {
            $chat_sid    = (string)$chat_row["chat_sid"];
            $chat_status = (string)$chat_row["chat_status"];

            $project_slug = (string)$chat_row["project_slug"];

            $chat_updated_at = (string)$chat_row["chat_updated_at"];

            $polling_meta_files = glob($messages_polling_dir . "/*/" . $chat_sid . "/*/meta.json");
            $polling_count      = is_array($polling_meta_files) ? count($polling_meta_files) : 0;

            $chat_item                 = [];
            $chat_item["project_slug"] = $project_slug;
            $chat_item["status"]       = $chat_status;
            $chat_item["lastupdate"]   = $chat_updated_at;
            $chat_item["polling"]      = $polling_count;

            $chat_map[$chat_sid] = $chat_item;
        }

        ksort($chat_map);

        return $chat_map;
    }
}
