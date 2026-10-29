<?php

namespace sc\app\hkai\plugins\sc_ai_health\monitor\prompt;

// == prompt fetch_entries：掃全部草稿，取 meta.json 最後修改時間（trait——掛入 prompt_Class） ==
// == 上游：prompt_Class；下游：data/messages/draft/*/*/meta.json（只用 stat，唔開檔） ==

trait fetch_entries
{
    // 取當刻 entries：{slug/sid: {lastupdate}}
    public function fetch_entries()
    {
        $draft_root = sc_get_config("hkai.messages_draft_dir");
        $draft_map  = [];

        // 判定：draft 根目錄唔存在 → 空
        if (is_dir($draft_root) === false) {
            return $draft_map;
        }

        // 掃描 {project_slug}/{chat_sid}/meta.json
        foreach (glob($draft_root . "/*/*/meta.json") as $meta_path) {
            $meta_stat = @stat($meta_path);

            // 判定：stat 失敗 → 跳過
            if ($meta_stat === false) {
                continue;
            }

            $draft_item_dir = dirname($meta_path);
            $project_dir    = dirname($draft_item_dir);

            $chat_sid     = basename($draft_item_dir);
            $project_slug = basename($project_dir);
            $draft_key    = $project_slug . "/" . $chat_sid;

            $draft_item               = [];
            $draft_item["lastupdate"] = date("Y-m-d H:i:s", (int)$meta_stat["mtime"]);

            $draft_map[$draft_key] = $draft_item;
        }

        ksort($draft_map);

        return $draft_map;
    }
}
