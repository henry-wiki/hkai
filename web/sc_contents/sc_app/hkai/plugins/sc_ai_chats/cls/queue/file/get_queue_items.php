<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\file;

// == sc_ai_chats queue get_queue_items：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 職責：讀排隊項目（每條 pending 一目錄：<prompt_id>／meta.json＋attachments） ==
// == 上游：shift／cap 檢查／UI／統計；下游：get_queue_chat_dir＋sc_read_json ==

trait get_queue_items
{
    // 排隊項目清單（目錄名升序＝FIFO——uuid7 按時間遞增；空＝[]）
    public function get_queue_items($project_slug, $chat_sid)
    {
        $queue_chat_dir = sc_ai_chats()->queue()->get_queue_chat_dir($project_slug, $chat_sid);

        $queue_item_dirs = glob($queue_chat_dir . "/*", GLOB_ONLYDIR);

        // 判定：冇目錄（未排過／讀唔到）→ 空清單
        if ($queue_item_dirs === false) {
            return [];
        }

        sort($queue_item_dirs);

        $queue_items = [];

        // 逐個目錄：讀 meta.json（原樣收入）
        foreach ($queue_item_dirs as $queue_item_path) {
            $meta_data = $this->_read_queue_json($queue_item_path . "/meta.json");

            // 判定：meta 缺失／損壞 → 略過（廢項目，唔阻隊列）
            if ($meta_data === null) {
                continue;
            }

            $queue_items[] = $meta_data;
        }

        return $queue_items;
    }

    // 讀 JSON 檔（缺失／損壞 → null）
    private function _read_queue_json($file_path)
    {
        // 判定：檔案唔存在 → null
        if (!is_file($file_path)) {
            return null;
        }

        $file_content = @file_get_contents($file_path);

        // 判定：讀唔到 → null
        if ($file_content === false) {
            return null;
        }

        $decoded_json = json_decode($file_content, true);

        // 判定：解析唔到 → null
        if (!is_array($decoded_json)) {
            return null;
        }

        return $decoded_json;
    }
}
