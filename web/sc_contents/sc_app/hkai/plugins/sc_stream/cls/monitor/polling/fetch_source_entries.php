<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\polling;

// == polling fetch_source_entries：取此 target 即時資料（trait——掛入 polling_Class） ==
// == 上游：本檔 init_snapshot()／monitor()->get_target_source_entries() ==
// == 下游：即時資料來源（DB／檔案）——唔係讀 snapshot.json ==

trait fetch_source_entries
{
    // 取當刻 polling 數（專案層＋對話層——同一份 glob 推導）
    public function fetch_source_entries()
    {
        $messages_polling_dir = sc_get_config("hkai.messages_polling_dir");

        $meta_files = glob($messages_polling_dir . "/*/*/*/meta.json");

        // 判定：glob 失敗 → 空
        if (is_array($meta_files) === false) {
            $meta_files = [];
        }

        $project_counts = [];
        $chat_counts    = [];


        foreach ($meta_files as $meta_file_item) {
            $relative_path = substr($meta_file_item, strlen($messages_polling_dir) + 1);
            $path_segments = explode("/", $relative_path);

            // 判定：路徑唔夠深（唔係 project/chat/message）→ 跳過
            if (count($path_segments) < 4) {
                continue;
            }

            $project_slug = $path_segments[0];
            $chat_sid     = $path_segments[1];

            $project_counts[$project_slug] = ($project_counts[$project_slug] ?? 0) + 1;
            $chat_counts[$project_slug][$chat_sid] = ($chat_counts[$project_slug][$chat_sid] ?? 0) + 1;
        }

        $polling_entries            = [];
        $polling_entries["project"] = $project_counts;
        $polling_entries["chat"]    = $chat_counts;

        return $polling_entries;
    }
}
