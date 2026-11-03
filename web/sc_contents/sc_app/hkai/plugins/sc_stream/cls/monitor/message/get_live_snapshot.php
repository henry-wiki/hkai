<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\message;

// == message get_live_snapshot：讀自己嘅快照檔（trait——掛入 message_Class） ==
// == 上游：write_snapshot／sync_snapshot_to_view／diff ==
// == 回傳：message/<chat_sid>.json（array）；冇檔／壞檔 → null ==

trait get_live_snapshot
{
    // 快照檔路徑：data/stream/message/<chat_sid>.json
    public function get_snapshot_file($chat_sid)
    {
        $snapshot_file = sc_get_config("hkai.stream_dir") . "/message/" . $chat_sid . ".json";

        return $snapshot_file;
    }

    // 讀自己嘅快照（冇檔／壞檔 → null）
    public function get_live_snapshot()
    {
        $chat_sid = sc_stream()->resolve_chat_sid();

        // 判定：冇對話 → null
        if ($chat_sid === "") {
            return null;
        }

        $snapshot_file = sc_stream()->monitor()->message()->get_snapshot_file($chat_sid);

        return sc_read_json($snapshot_file);
    }
}
