<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft\action;

// == sc_ai_chats draft draft_create：建立草稿（trait——掛入 draft_Class） ==
// == 職責：POST chat_sid → 建 draft/<slug>/<chat_sid>/attachments/ → 寫 meta.json ==
// == 上游：request/draft/draft_create.php；下游：draft_Class set_draft_meta／get_draft_meta ==

trait draft_create
{

    // POST draft_create：建立／取用一個草稿目錄（同一 chat_sid 重入＝同一目錄）
    public function draft_create()
    {
        $input_data = sc_request()->json();

        $project_slug = trim((string)($input_data["project_slug"] ?? ""));
        $chat_sid     = trim((string)($input_data["chat_sid"] ?? ""));
        $updated_by   = trim((string)($input_data["updated_by"] ?? ""));

        // 判定：目錄 key 格式唔合 → 400（防路徑穿越）
        if (!sc_ai_chats()->chat()->is_valid_chat_sid($chat_sid)) {
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

        // 新對話標記（DB 未有 chat row＝1；有＝0）
        $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);

        // 只交本次要改嘅欄（其餘由 set_draft_meta 以模板／現有值補齊）
        $meta_data = [];
        $meta_data["status"]       = "draft";
        $meta_data["is_new_chat"]  = ($chat_row === null) ? 1 : 0;
        $meta_data["project_id"]   = (int)$project_row["project_id"];
        $meta_data["project_path"] = (string)$project_row["project_path"];

        // 判定：對話列已存在 → 記錄 chat_id（新對話＝0，上帶時補上）
        if ($chat_row === null) {
            $meta_data["chat_id"] = 0;
        } else {
            $meta_data["chat_id"] = (int)$chat_row["chat_id"];
        }

        // 判定：有帶 updated_by → 用佢；冇帶（""）→ null（未提交）
        if ($updated_by !== "") {
            $meta_data["updated_by"] = $updated_by;
        } else {
            $meta_data["updated_by"] = null;
        }

        $meta_written = sc_ai_chats()->draft()->set_draft_meta($project_slug, $chat_sid, $meta_data);

        // 判定：meta 寫入失敗 → 500
        if ($meta_written === false) {
            return sc_response()->json(["ok" => false, "error" => "cannot write draft meta"], 500);
        }

        // 寫入後嘅完整 meta（回應帶回）
        $saved_meta = sc_ai_chats()->draft()->get_draft_meta($project_slug, $chat_sid);

        return sc_response()->json([
            "ok"           => true,
            "prompt_id"    => $saved_meta["prompt_id"],
            "chat_sid"     => $chat_sid,
            "project_slug" => $project_slug,
            "meta"         => $saved_meta,
        ]);
    }
}
