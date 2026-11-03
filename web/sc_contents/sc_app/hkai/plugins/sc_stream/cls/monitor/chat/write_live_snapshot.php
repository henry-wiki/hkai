<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\chat;

// == chat write_live_snapshot：寫自己嘅即時快照（trait——掛入 chat_Class） ==
// == 上游：本檔 init_snapshot() ==
// == 回傳：寫入嘅 target（rev／lastupdate／entries）；失敗 → null ==

trait write_live_snapshot
{
    // 寫自己嘅快照（回 target；內部自行讀舊檔算 rev）
    public function write_live_snapshot($live_entries)
    {
        $saved_snapshot = $this->get_live_snapshot();
        $saved_state    = null;

        $write_time = time();

        // 判定：有舊快照 → 取 state
        if ($saved_snapshot !== null) {
            $saved_state = $saved_snapshot["state"] ?? null;
        }

        $snapshot_state = [];
        $snapshot_state["rev"] = 0;
        $snapshot_state["lastupdate"] = date("Y-m-d H:i:s");
        $snapshot_state["target_last_update_at"] = $write_time;
        $snapshot_state["entries"] = $live_entries;

        if(isset($saved_state))
        {
            $snapshot_state["rev"] = @intval($saved_state["rev"]) + 1;
        }


        
        $snapshot_data               = [];
        $snapshot_data["created_at"] = null;
        $snapshot_data["updated_at"] = date("Y-m-d H:i:s");
        $snapshot_data["state"]      = $snapshot_state;

        // 判定：冇舊檔 → 用現在時間
        if (isset($saved_snapshot)) {
            $snapshot_data["created_at"] = (string)$saved_snapshot["created_at"];
        }
        else {
            $snapshot_data["created_at"] = date("Y-m-d H:i:s");
        }

        $stream_dir  = sc_get_config("hkai.stream_dir");
        $target_file = $stream_dir . "/chat/snapshot.json";

        $is_written = sc_write_json($target_file, $snapshot_data);

        // 判定：寫入失敗 → 記錄、唔同步閘
        if ($is_written === false) {
            hkai()->runtime()->logger()->error("write snapshot failed: " . $target_file);

            return null;
        }

        // 寫入成功 → 閘同步為同一時間（下 tick 唔需要重掃）
        return $snapshot_state;
    }
}
