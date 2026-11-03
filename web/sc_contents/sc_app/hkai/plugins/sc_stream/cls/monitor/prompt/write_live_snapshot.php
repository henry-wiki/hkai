<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\prompt;

// == prompt write_live_snapshot：寫本頁對話草稿快照（trait——掛入 prompt_Class） ==
// == 上游：sse_prompt()->get_snapshot_changes_for_prompt() ==
// == 下游：data/stream/prompt/<project_slug>/<chat_sid>.json（原子寫） ==

trait write_live_snapshot
{
    // 寫本對話快照（自行讀舊檔算 rev／沿用 created_at）
    public function write_live_snapshot($live_entries)
    {
        $project_slug = sc_stream()->resolve_project_slug();
        $chat_sid     = sc_stream()->resolve_chat_sid();

        // 判定：未帶齊 key → null
        if ($project_slug === "" || $chat_sid === "") {
            return null;
        }

        $saved_snapshot = sc_stream()->monitor()->prompt()->get_live_snapshot();

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

        $prompt_snapshot               = [];
        $prompt_snapshot["created_at"] = $created_at;
        $prompt_snapshot["updated_at"] = date("Y-m-d H:i:s");
        $prompt_snapshot["state"]      = $snapshot_state;

        $snapshot_file = sc_stream()->monitor()->prompt()->get_snapshot_file($project_slug, $chat_sid);

        $snapshot_dir = dirname($snapshot_file);

        sc_mkdir($snapshot_dir);

        sc_write_json($snapshot_file, $prompt_snapshot);

        return $prompt_snapshot;
    }
}
