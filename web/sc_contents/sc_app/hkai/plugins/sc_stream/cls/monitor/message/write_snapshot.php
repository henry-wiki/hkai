<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\message;

// == message write_snapshot：寫本對話訊息快照（trait——掛入 message_Class） ==
// == 上游：本檔 init_snapshot() ==
// == 下游：data/stream/message/<chat_sid>.json（原子寫） ==

trait write_snapshot
{
    // 寫本對話快照（回 snapshot；內部自行讀舊檔算 rev）
    public function write_snapshot($live_entries)
    {
        $chat_sid = sc_stream()->resolve_chat_sid();

        $saved_snapshot = sc_stream()->monitor()->message()->get_live_snapshot();

        $snapshot_state = [];
        $snapshot_state["rev"]        = 0;
        $snapshot_state["lastupdate"] = date("Y-m-d H:i:s");
        $snapshot_state["entries"]    = $live_entries;

        // 判定：已有舊快照 → rev +1
        if ($saved_snapshot !== null) {
            $saved_state = $saved_snapshot["state"] ?? null;

            // 判定：舊 state 存在 → 沿用 rev 遞增
            if (is_array($saved_state) === true) {
                $snapshot_state["rev"] = (int)($saved_state["rev"] ?? 0) + 1;
            }
        }

        $created_at = date("Y-m-d H:i:s");

        // 判定：舊快照有 created_at → 沿用
        if ($saved_snapshot !== null) {
            $saved_created_at = $saved_snapshot["created_at"] ?? "";

            // 判定：有值 → 沿用
            if ($saved_created_at !== "") {
                $created_at = $saved_created_at;
            }
        }

        $message_snapshot               = [];
        $message_snapshot["created_at"] = $created_at;
        $message_snapshot["updated_at"] = date("Y-m-d H:i:s");
        $message_snapshot["state"]      = $snapshot_state;

        $snapshot_file = sc_stream()->monitor()->message()->get_snapshot_file($chat_sid);

        $snapshot_dir = dirname($snapshot_file);

        sc_mkdir($snapshot_dir);

        sc_write_json($snapshot_file, $message_snapshot);

        return $message_snapshot;
    }
}
