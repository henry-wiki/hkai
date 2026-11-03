<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\message;

// == message fetch_source_entries：即時由 DB 取當前對話訊息狀態（trait——掛入 message_Class） ==
// == 上游：本檔 init_snapshot()／monitor()->get_target_source_entries() ==
// == 下游：DB messages（status／lastupdate）——polling 由 polling target 專責 ==

trait fetch_source_entries
{
    // 取指定對話的訊息 entries：{message_id: {status, lastupdate}}
    public function fetch_source_entries()
    {
        $message_map = [];

        $chat_sid = sc_stream()->resolve_chat_sid();

        // 判定：冇對話 → 空
        if ($chat_sid === "") {
            return $message_map;
        }

        $message_rows = sc_db()->connection("sqlite")->select(
            "SELECT m.message_id, m.message_status, m.message_update_at"
            . " FROM messages m"
            . " JOIN chats c ON c.chat_id = m.chat_id"
            . " WHERE c.chat_sid = ?"
            . " ORDER BY m.message_id DESC LIMIT 100",
            [$chat_sid]
        );

        foreach ($message_rows as $message_row) {
            $message_id = (int)$message_row["message_id"];

            $message_key = (string)$message_id;

            $message_item               = [];
            $message_item["status"]     = (string)$message_row["message_status"];
            $message_item["lastupdate"] = (string)$message_row["message_update_at"];

            $message_map[$message_key] = $message_item;
        }

        ksort($message_map);

        return $message_map;
    }
}
