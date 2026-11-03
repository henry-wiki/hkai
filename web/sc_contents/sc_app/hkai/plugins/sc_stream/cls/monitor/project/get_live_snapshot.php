<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\project;

// == project get_live_snapshot：讀自己嘅快照檔（trait——掛入 project_Class） ==
// == 上游：各 trait／monitor()->get_target_snapshot() ==
// == 回傳：整個 snapshot.json（array）；冇檔／壞檔 → null ==

trait get_live_snapshot
{
    // 讀自己嘅快照（冇檔／壞檔 → null）
    public function get_live_snapshot()
    {
        $stream_dir  = sc_get_config("hkai.stream_dir");
        $target_file = $stream_dir . "/project/snapshot.json";

        // 判定：冇檔 → null
        if (is_file($target_file) === false) {
            return null;
        }

        $target_text = @file_get_contents($target_file);

        // 判定：讀唔到 → null
        if ($target_text === false) {
            return null;
        }

        $snapshot_data = json_decode($target_text, true);

        // 判定：解析失敗 → null
        if (is_array($snapshot_data) === false) {
            return null;
        }

        return $snapshot_data;
    }
}
