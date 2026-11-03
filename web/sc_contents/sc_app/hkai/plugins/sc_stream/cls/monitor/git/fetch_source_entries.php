<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\git;

// == git fetch_source_entries：取此 target 即時資料（trait——掛入 git_Class） ==
// == 上游：本檔 init_snapshot()／monitor()->get_target_source_entries() ==
// == 下游：即時資料來源（DB／檔案）——唔係讀 snapshot.json ==

trait fetch_source_entries
{
    // 取當刻 entries：只放當前專案一條（未選專案 → 空 map）
    public function fetch_source_entries()
    {
        $project_slug = sc_stream()->resolve_project_slug();

        $git_map = [];

        // 判定：冇當前專案 → 空 map
        if ($project_slug === "") {
            return $git_map;
        }

        $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        // 判定：查唔到專案 → 空 map
        if ($project_row === null) {
            return $git_map;
        }

        $project_path = (string)$project_row["project_path"];

        $git_entry = sc_stream()->monitor()->git()->build_git_entry($project_path);

        $git_map[$project_slug] = $git_entry;

        return $git_map;
    }
}
