<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\message;

// == message sync_snapshot_to_view：把本 target 快照寫入本頁 view 檔（trait——掛入 message_Class） ==
// == 上游：get_snapshot_changes_for_state（每 tick） ==
// == 下游：data/stream/message/view/<view_id>.json ==

trait sync_snapshot_to_view
{
    // 寫本頁 view 檔（冇快照／冇 view id → false）
    public function sync_snapshot_to_view($view_id)
    {
        // 判定：冇 view id → 唔寫
        if ($view_id === "") {
            return false;
        }

        $saved_snapshot = sc_stream()->monitor()->message()->get_live_snapshot();

        // 判定：未有快照 → 唔寫
        if ($saved_snapshot === null) {
            return false;
        }

        $snapshot_state = $saved_snapshot["state"] ?? null;

        // 判定：快照內容唔係陣列 → 唔寫
        if (is_array($snapshot_state) === false) {
            return false;
        }

        $view_file = sc_get_config("hkai.stream_dir") . "/message/view/" . $view_id . ".json";

        $view_data          = [];
        $view_data["state"] = $snapshot_state;

        return sc_write_json($view_file, $view_data);
    }
}
