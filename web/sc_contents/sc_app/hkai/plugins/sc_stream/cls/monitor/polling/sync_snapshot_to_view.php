<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\polling;

// == polling sync_snapshot_to_view：把本 target 快照寫入本頁 view 檔（trait——掛入 polling_Class） ==
// == 上游：get_snapshot_changes_for_state（建基線）、snapshot::init_snapshot() ==
// == 下游：data/stream/polling/view/<view_id>.json ==

trait sync_snapshot_to_view
{
    // 寫本頁 view 檔（冇快照／冇 viewer id → false）
    public function sync_snapshot_to_view($view_id)
    {
        // 判定：冇 viewer id → 唔寫
        if ($view_id === "") {
            return false;
        }

        // 判定：此 target 未 ready → 唔寫
        if ($this->is_ready() === false) {
            return false;
        }

        $saved_snapshot = $this->get_live_snapshot();

        // 判定：未有快照 → 唔寫
        if ($saved_snapshot === null) {
            return false;
        }

        $snapshot_state = $saved_snapshot["state"] ?? null;

        // 判定：快照內容唔係陣列 → 唔寫
        if (is_array($snapshot_state) === false) {
            return false;
        }

        $view_file = sc_stream()->snapshot()->get_snapshot_view_file("polling", $view_id);

        $view_data          = [];
        $view_data["state"] = $snapshot_state;

        return sc_write_json($view_file, $view_data);
    }
}
