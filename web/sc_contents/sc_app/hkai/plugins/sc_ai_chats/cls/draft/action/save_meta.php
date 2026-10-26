<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft\action;

// == sc_ai_chats draft save_meta：即時保存草稿資料（trait——掛入 draft_Class） ==
// == 職責：文字／provider／model／reasoning／chat_mode → meta（agent 由後台現行值蓋章） ==
// == 上游：request/draft/draft_save_meta.php；下游：draft_Class update_meta ==

trait save_meta
{

    // POST draft_save_meta：任何欄位有變 → 即時寫 meta
    public function draft_save_meta()
    {
        $input_data = sc_request()->json();

        $project_slug = trim((string)($input_data["project_slug"] ?? ""));
        $chat_sid     = trim((string)($input_data["chat_sid"] ?? ""));
        $draft_key    = $this->get_draft_key($chat_sid);

        // 判定：目錄 key 格式唔合 → 400
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

        $this->ensure_meta($project_slug, $draft_key, $chat_sid);

        $meta_patch = [];
        $meta_patch["chat_sid"] = $chat_sid;

        // 判定：有帶文字 → 更新
        if (isset($input_data["prompt_text"])) {
            $meta_patch["prompt_text"] = (string)$input_data["prompt_text"];
        }

        // 判定：有帶 provider → 更新
        if (isset($input_data["provider_id"])) {
            $meta_patch["provider_id"] = (string)$input_data["provider_id"];
        }

        // 判定：有帶 model → 更新
        if (isset($input_data["model_id"])) {
            $meta_patch["model_id"] = (string)$input_data["model_id"];
        }

        // 判定：有帶推理等級 → 更新
        if (isset($input_data["reasoning_effort"])) {
            $meta_patch["reasoning_effort"] = (string)$input_data["reasoning_effort"];
        }

        // 判定：有帶 chat mode → 更新
        if (isset($input_data["chat_mode"])) {
            $meta_patch["chat_mode"] = (string)$input_data["chat_mode"];
        }

        // agent（後台現行值——系統讀嘅 id）
        $meta_patch["agent"] = (string)hkai()->agent()->get_agent();

        // 新對話標記（DB 未有 chat row＝1；有＝0）
        $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);
        $meta_patch["is_new_chat"] = ($chat_row === null) ? 1 : 0;

        $saved = $this->update_meta($project_slug, $draft_key, $meta_patch);

        return sc_response()->json([
            "ok"        => ($saved === true),
            "draft_key" => $draft_key,
        ]);
    }
}
