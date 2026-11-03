<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\prompt;

// == prompt get_live_snapshot：讀自己嘅快照檔（trait——掛入 prompt_Class） ==
// == 上游：write_live_snapshot／sync_snapshot_to_view／sse_prompt()->get_snapshot_changes_for_prompt() ==
// == 回傳：prompt/<project_slug>/<chat_sid>.json（array）；冇檔／壞檔 → null ==

trait get_live_snapshot
{
    // 快照檔路徑：data/stream/prompt/<project_slug>/<chat_sid>.json
    public function get_snapshot_file($project_slug, $chat_sid)
    {
        $snapshot_file = sc_get_config("hkai.stream_dir") . "/prompt/" . $project_slug . "/" . $chat_sid . ".json";

        return $snapshot_file;
    }

    // 讀自己嘅快照（冇檔／壞檔 → null）
    public function get_live_snapshot()
    {
        $project_slug = sc_stream()->resolve_project_slug();
        $chat_sid     = sc_stream()->resolve_chat_sid();

        // 判定：未帶齊 key → null
        if ($project_slug === "" || $chat_sid === "") {
            return null;
        }

        $snapshot_file = sc_stream()->monitor()->prompt()->get_snapshot_file($project_slug, $chat_sid);

        return sc_read_json($snapshot_file);
    }
}
