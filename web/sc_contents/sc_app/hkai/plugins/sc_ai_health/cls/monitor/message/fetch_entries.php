<?php

namespace sc\app\hkai\plugins\sc_ai_health\monitor\message;

// == message fetch_entries：當前對話的訊息狀態（trait——掛入 message_Class） ==
// == 上游：health_stream（每條 SSE 連線各自叫，只做自己嗰個對話） ==
// == 下游：DB messages（status／lastupdate）＋ messages/polling 目錄（有冇實際工作） ==

trait fetch_entries
{
    // 取指定對話的訊息 entries：{chat_sid/message_id: {status, lastupdate, polling}}
    public function fetch_chat_entries($project_slug, $chat_sid)
    {
        $message_map = [];

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

            $message_key = $chat_sid . "/" . $message_id;

            $polling_dir = sc_ai_chats()->poll()->get_poll_dir_by_identity($project_slug, $chat_sid, $message_id);

            $polling_count = 0;

            // 判定：polling 目錄存在 → 有實際工作（1）
            if (is_dir($polling_dir) === true) {
                $polling_count = 1;
            }

            $message_item               = [];
            $message_item["status"]     = (string)$message_row["message_status"];
            $message_item["lastupdate"] = (string)$message_row["message_update_at"];
            $message_item["polling"]    = $polling_count;

            $message_map[$message_key] = $message_item;
        }

        ksort($message_map);

        return $message_map;
    }
}
