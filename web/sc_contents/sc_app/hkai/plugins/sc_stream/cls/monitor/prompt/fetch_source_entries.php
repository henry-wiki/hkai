<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\prompt;

// == prompt fetch_source_entries：即時由檔案取本頁對話草稿狀態（trait——掛入 prompt_Class） ==
// == 上游：本檔 init_snapshot()／sse_prompt()->get_snapshot_changes_for_prompt()／monitor()->get_target_source_entries() ==

trait fetch_source_entries
{
    // 取當刻 entries：只取指定一份 → {project_slug: {chat_sid: {lastupdate, draft}}}（draft＝draft_fetch 內容；無此草稿＝空）
    public function fetch_source_entries()
    {
        $draft_map = [];

        $project_slug = sc_stream()->resolve_project_slug();
        $chat_sid     = sc_stream()->resolve_chat_sid();

        // 判定：未帶齊 key → 空（唔掃任何目錄）
        if ($project_slug === "") {
            return $draft_map;
        }

        // 判定：冇對話 → 空
        if ($chat_sid === "") {
            return $draft_map;
        }

        $draft_root = sc_get_config("hkai.messages_draft_dir");
        $meta_path  = $draft_root . "/" . $project_slug . "/" . $chat_sid . "/meta.json";

        $meta_stat = @stat($meta_path);

        // 判定：冇此草稿 → 空
        if ($meta_stat === false) {
            return $draft_map;
        }

        $draft_item               = [];
        $draft_item["lastupdate"] = date("Y-m-d H:i:s", (int)$meta_stat["mtime"]);

        // 草稿內容（同 draft_fetch 回應形——前端可直接套用，免再打 request）
        $draft_item["draft"] = sc_ai_chats()->draft()->build_draft_result($project_slug, $chat_sid);

        $draft_map[$project_slug][$chat_sid] = $draft_item;

        return $draft_map;
    }
}
