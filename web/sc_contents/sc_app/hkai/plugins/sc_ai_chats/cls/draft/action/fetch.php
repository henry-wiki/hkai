<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft\action;

// == sc_ai_chats draft fetch：讀取草稿（trait——掛入 draft_Class） ==
// == 職責：POST chat_sid → meta（文字／設定／附件清單＋縮圖 data URL） ==
// == 上游：request/draft/draft_fetch.php；下游：draft_Class read_meta／read_thumb_data_url ==

trait fetch
{

    // POST draft_fetch：回草稿內容（重載／另一裝置拉狀態用）
    public function draft_fetch()
    {
        $input_data = sc_request()->json();

        $chat_sid     = trim((string)($input_data["chat_sid"] ?? ""));
        $project_slug = trim((string)($input_data["project_slug"] ?? ""));
        $draft_key    = $this->get_draft_key($chat_sid);

        // 判定：目錄 key 格式唔合 → 400（防路徑穿越）
        if (!$this->is_valid_draft_key($draft_key)) {
            return sc_response()->json(["ok" => false, "error" => "invalid chat_sid"], 400);
        }

        // 專案列（空／無效 slug → fallback Home）
        $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        // 判定：查唔到 → 查 Home
        if ($project_row === null) {
            $project_row = sc_ai_projects()->project()->get_project_row(sc_get_config("hkai.default_project_slug"));
        }

        // 判定：連 Home 都冇 → 嚴重錯
        if ($project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "home project not found"], 500);
        }

        $project_slug = (string)$project_row["project_slug"];
        $draft_dir    = $this->get_draft_item_dir($project_slug, $draft_key);
        $meta_data    = $this->read_meta($draft_dir);

        // 判定：冇 meta → 200 空結果（無草稿係常態——前端靜默，唔當錯）
        if (count($meta_data) < 1) {
            return sc_response()->json([
                "ok"           => true,
                "draft_id"     => "",
                "draft_key"    => $draft_key,
                "project_slug" => $project_slug,
                "meta"         => [],
                "attachments"  => [],
            ]);
        }

        // 判定：meta 未帶 attachments → 補空清單
        if (!isset($meta_data["attachments"]) || !is_array($meta_data["attachments"])) {
            $meta_data["attachments"] = [];
        }

        $attachment_list = [];

        // 逐項加縮圖 data URL＋檔案大小（重載即見圖）
        foreach ($meta_data["attachments"] as $attachment_item) {
            $thumb_filename = (string)($attachment_item["thumb"] ?? "");
            $file_name      = (string)($attachment_item["filename"] ?? "");
            $file_path      = $draft_dir . "/attachments/" . $file_name;

            $attachment_item["thumb_data_url"] = $this->read_thumb_data_url($project_slug, $draft_key, $thumb_filename);
            $attachment_item["file_size"]      = 0;

            // 判定：檔案存在 → 取大小
            if (is_file($file_path)) {
                clearstatcache(true, $file_path);
                $attachment_item["file_size"] = (int)filesize($file_path);
            }

            $attachment_list[] = $attachment_item;
        }

        return sc_response()->json([
            "ok"           => true,
            "draft_id"     => $meta_data["draft_id"] ?? "",
            "draft_key"    => $draft_key,
            "project_slug" => $project_slug,
            "meta"         => $meta_data,
            "attachments"  => $attachment_list,
        ]);
    }
}
