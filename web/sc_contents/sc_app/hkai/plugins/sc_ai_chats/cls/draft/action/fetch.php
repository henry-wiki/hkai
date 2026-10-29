<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft\action;

// == sc_ai_chats draft fetch：讀取草稿（trait——掛入 draft_Class） ==
// == 職責：POST chat_sid → meta（文字／設定／附件清單＋縮圖 data URL） ==
// == 上游：request/draft/draft_fetch.php；下游：draft_Class get_draft_meta／read_thumb_data_url ==

trait fetch
{

    // POST draft_fetch：回草稿內容（重載／另一裝置拉狀態用）
    public function draft_fetch()
    {
        $input_data = sc_request()->json();

        $chat_sid     = trim((string)($input_data["chat_sid"] ?? ""));
        $project_slug = trim((string)($input_data["project_slug"] ?? ""));

        // 判定：目錄 key 格式唔合 → 400（防路徑穿越）
        if (!sc_ai_chats()->chat()->is_valid_chat_sid($chat_sid)) {
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
        $meta_data    = sc_ai_chats()->draft()->get_draft_meta($project_slug, $chat_sid);

        // 判定：冇 meta → 200 空結果（無草稿係常態——前端靜默，唔當錯）
        if (count($meta_data) < 1) {
            return sc_response()->json([
                "ok"           => true,
                "prompt_id"    => "",
                "chat_sid"  => $chat_sid,
                "project_slug" => $project_slug,
                "meta"         => [],
                "attachments"  => [],
            ]);
        }

        // 清單以附件 json 為真相 → 重建（有 meta 才做，避免讀取層建草稿）
        sc_ai_chats()->draft()->refresh_draft_meta_for_attachments($project_slug, $chat_sid);
        $meta_data = sc_ai_chats()->draft()->get_draft_meta($project_slug, $chat_sid);

        // 判定：meta 未帶 attachments → 補空清單
        if (!isset($meta_data["attachments"]) || !is_array($meta_data["attachments"])) {
            $meta_data["attachments"] = [];
        }

        $attachment_list = [];
        $draft_file_paths = [];

        // 磁碟實檔（檔名 → 絕對路徑）
        foreach (sc_ai_chats()->draft()->list_draft_files($project_slug, $chat_sid) as $draft_file_item) {
            $draft_file_paths[$draft_file_item["filename"]] = $draft_file_item["path"];
        }

        // 逐項加縮圖 data URL＋檔案大小（重載即見圖；檔案已失＝剔走＋稍後回寫 meta）
        foreach ($meta_data["attachments"] as $attachment_item) {
            $thumb_filename = (string)($attachment_item["thumb"] ?? "");
            $file_name      = (string)($attachment_item["filename"] ?? "");

            // 判定：磁碟冇呢個檔（清單殘留）→ 剔走（self-heal）
            if (!isset($draft_file_paths[$file_name])) {
                continue;
            }

            $file_path = $draft_file_paths[$file_name];

            $attachment_item["thumb_data_url"] = sc_ai_chats()->draft()->read_thumb_data_url($project_slug, $chat_sid, $thumb_filename);
            $attachment_item["file_size"]      = 0;

            clearstatcache(true, $file_path);
            $attachment_item["file_size"] = (int)filesize($file_path);

            $attachment_list[] = $attachment_item;
        }

        // chat_mode 舊值正規化（chat／auto／smart → UI 模 key；空值／冇 key 一律唔動）
        $has_chat_mode_key = array_key_exists("chat_mode", $meta_data);

        // 判定：有呢個 key 且非空 → 正規化
        if ($has_chat_mode_key === true && (string)$meta_data["chat_mode"] !== "") {
            $meta_data["chat_mode"] = sc_ai_chats()->chat_option()->get_chat_mode((string)$meta_data["chat_mode"]);
        }

        return sc_response()->json([
            "ok"           => true,
            "prompt_id"    => $meta_data["prompt_id"] ?? "",
            "chat_sid"  => $chat_sid,
            "project_slug" => $project_slug,
            "meta"         => $meta_data,
            "attachments"  => $attachment_list,
        ]);
    }
}
