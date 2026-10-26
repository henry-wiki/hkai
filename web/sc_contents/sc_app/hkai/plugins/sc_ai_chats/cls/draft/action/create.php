<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft\action;

// == sc_ai_chats draft create：建立草稿（trait——掛入 draft_Class） ==
// == 職責：POST chat_sid → 建 draft/<slug>/<chat_sid|new>/attachments/ → 寫 meta.json ==
// == 上游：request/draft/draft_create.php；下游：draft_Class 路徑／meta 方法＋sc_uuid7() ==

trait create
{

    // POST draft_create：建立／取用一個草稿目錄（同一 chat_sid 重入＝同一目錄）
    public function draft_create()
    {
        $input_data = sc_request()->json();

        $project_slug = trim((string)($input_data["project_slug"] ?? ""));
        $chat_sid     = trim((string)($input_data["chat_sid"] ?? ""));
        $updated_by   = trim((string)($input_data["updated_by"] ?? ""));
        $draft_key    = $this->get_draft_key($chat_sid);

        // 判定：目錄 key 格式唔合 → 400（防路徑穿越）
        if (!$this->is_valid_draft_key($draft_key)) {
            return sc_response()->json(["ok" => false, "error" => "invalid chat_sid"], 400);
        }

        // 專案列（空／無效 slug → fallback Home——與 prompt_send 同一規則）
        $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        // 判定：查唔到 → 查 Home
        if ($project_row === null) {
            $project_row = sc_ai_projects()->project()->get_project_row(sc_get_config("hkai.default_project_slug"));
        }

        // 判定：連 Home 都冇 → 嚴重錯
        if ($project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "home project not found"], 500);
        }

        $project_slug    = (string)$project_row["project_slug"];
        $draft_dir       = $this->get_draft_item_dir($project_slug, $draft_key);
        $attachments_dir = $draft_dir . "/attachments";

        // 舊制遷移：新 prompt 已配真 sid，但舊草稿仍在 "new" → 認領搬過去（只做一次）
        $migrated = false;

        // 判定：有真 sid 且目標未有 → 認領舊 "new" 草稿
        if ($draft_key !== "new") {
            $migrated = $this->claim_new_draft($project_slug, $draft_key);
        }

        // 建草稿目錄（遞歸——首次建立整個樹）
        $made = @mkdir($attachments_dir, 0777, true);

        // 判定：建目錄失敗且目錄唔存在 → 500
        if ($made === false && !is_dir($attachments_dir)) {
            return sc_response()->json(["ok" => false, "error" => "cannot create draft dir"], 500);
        }

        $existing_meta = $this->read_meta($draft_dir);
        $now_milliseconds = (int)floor(microtime(true) * 1000);

        $meta_data = [];
        $meta_data["draft_id"]     = $existing_meta["draft_id"] ?? sc_uuid7();
        $meta_data["draft_key"]    = $draft_key;
        $meta_data["project_slug"] = $project_slug;
        $meta_data["chat_sid"]     = $chat_sid;
        $meta_data["prompt_text"]  = (string)($existing_meta["prompt_text"] ?? "");
        $meta_data["status"]       = "active";

        // 新對話標記（DB 未有 chat row＝1；有＝0）
        $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);
        $meta_data["is_new_chat"] = ($chat_row === null) ? 1 : 0;
        $meta_data["updated_by"]   = $updated_by;
        $meta_data["created_at"]   = (int)($existing_meta["created_at"] ?? $now_milliseconds);
        $meta_data["updated_at"]   = $now_milliseconds;

        $meta_written = $this->write_meta($draft_dir, $meta_data);

        // 判定：meta 寫入失敗 → 500（目錄留待 sweep 清）
        if ($meta_written === false) {
            return sc_response()->json(["ok" => false, "error" => "cannot write draft meta"], 500);
        }

        return sc_response()->json([
            "ok"           => true,
            "draft_id"     => $meta_data["draft_id"],
            "draft_key"    => $draft_key,
            "project_slug" => $project_slug,
            "migrated"     => $migrated,
            "meta"         => $meta_data,
        ]);
    }
}
